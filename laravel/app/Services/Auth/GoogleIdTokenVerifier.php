<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifies a Google ID token (the JWT that Google Identity Services hands
 * the browser after "Continue with Google").
 *
 * Done here rather than trusted from the browser: the token is checked
 * against Google's published signing keys, and its issuer, audience,
 * expiry and nonce must all match before anyone is signed in. No client
 * secret is involved anywhere in this flow — only the public client ID —
 * so there is no secret to leak.
 *
 * Google's certificates rotate, so they are cached for as long as Google's
 * own Cache-Control header allows and refetched once if a token arrives
 * signed with a key we have not seen yet.
 */
class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    private const CACHE_KEY = 'auth.google.certs';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /** Seconds of clock drift tolerated between this server and Google. */
    private const LEEWAY = 120;

    /**
     * @return array<string, mixed> the verified claims
     *
     * @throws GoogleTokenException when the token must not be trusted
     */
    public function verify(string $jwt, string $clientId, ?string $expectedNonce): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new GoogleTokenException('malformed');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (! is_array($header) || ! is_array($claims) || $signature === '') {
            throw new GoogleTokenException('malformed');
        }

        // Pin the algorithm. Accepting whatever the header asks for is how
        // "alg: none" and HS256 key-confusion attacks get in.
        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw new GoogleTokenException('unsupported algorithm');
        }

        $pem = $this->certificate($header['kid']);
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new GoogleTokenException('unreadable certificate');
        }

        if (openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new GoogleTokenException('bad signature');
        }

        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new GoogleTokenException('wrong issuer');
        }

        // A token minted for someone else's app must not open this one.
        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array($clientId, $audience, true)) {
            throw new GoogleTokenException('wrong audience');
        }

        $now = time();

        if (! is_numeric($claims['exp'] ?? null) || $claims['exp'] < $now - self::LEEWAY) {
            throw new GoogleTokenException('expired');
        }

        if (is_numeric($claims['iat'] ?? null) && $claims['iat'] > $now + self::LEEWAY) {
            throw new GoogleTokenException('issued in the future');
        }

        // The nonce ties the token to this browser session, so a token
        // lifted from somewhere else cannot be replayed here.
        if ($expectedNonce === null || ! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new GoogleTokenException('nonce mismatch');
        }

        if (! is_string($claims['sub'] ?? null) || ! is_string($claims['email'] ?? null)) {
            throw new GoogleTokenException('missing identity');
        }

        return $claims;
    }

    private function certificate(string $kid): string
    {
        $certs = Cache::get(self::CACHE_KEY);

        if (! is_array($certs) || ! isset($certs[$kid])) {
            // Unknown key id: Google may have rotated. Refetch once.
            $certs = $this->fetchCertificates();
        }

        if (! isset($certs[$kid]) || ! is_string($certs[$kid])) {
            throw new GoogleTokenException('unknown key');
        }

        return $certs[$kid];
    }

    /** @return array<string, string> */
    private function fetchCertificates(): array
    {
        try {
            $response = Http::timeout(6)->acceptJson()->get(self::CERTS_URL);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not reach Google to verify the sign-in.', 0, $e);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Google returned an unexpected response while verifying the sign-in.');
        }

        $certs = $response->json();

        preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $match);
        $ttl = isset($match[1]) ? max(300, min((int) $match[1], 86400)) : 3600;

        Cache::put(self::CACHE_KEY, $certs, $ttl);

        return $certs;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? '' : $decoded;
    }
}
