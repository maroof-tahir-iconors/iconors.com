<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\GoogleTokenException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Continue with Google".
 *
 * The browser gets an ID token from Google Identity Services and posts it
 * here (with the usual CSRF token). This verifies it, then signs the
 * person in through the same session guard the email/password login uses:
 *
 *   - an existing account with that email is signed in;
 *   - otherwise a new Free Customer account is created, exactly as the
 *     registration form would, with an unusable random password.
 *
 * Responses are JSON because the request comes from the page's script;
 * the script follows `redirect` on success and shows `message` otherwise.
 */
class GoogleAuthController extends Controller
{
    public const NONCE_KEY = 'auth.google.nonce';

    public function __invoke(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            return $this->fail('Google sign-in is not available right now. Please use your email and password.', 503);
        }

        // Already signed in (a second tab, a double click): nothing to do.
        if (Auth::check()) {
            return response()->json(['redirect' => route('dashboard')]);
        }

        $credential = $request->input('credential');

        if (! is_string($credential) || $credential === '' || strlen($credential) > 4096) {
            return $this->fail('Google did not send a sign-in. Please try again.', 422);
        }

        try {
            $claims = $verifier->verify($credential, $clientId, $request->session()->get(self::NONCE_KEY));
        } catch (GoogleTokenException $e) {
            Log::notice('Google sign-in rejected: '.$e->getMessage(), ['ip' => $request->ip()]);

            return $this->fail('We could not verify your Google sign-in. Refresh the page and try again.', 401);
        } catch (\RuntimeException $e) {
            report($e);

            return $this->fail('Google could not be reached to confirm your sign-in. Please try again in a moment.', 503);
        }

        // Google only vouches for an address it has verified. Signing
        // someone into an account by an unverified address would let a
        // stranger claim it.
        $verified = $claims['email_verified'] ?? false;

        if ($verified !== true && $verified !== 'true') {
            return $this->fail('Your Google account’s email address is not verified yet. Verify it with Google, or sign up with email instead.', 403);
        }

        $email = Str::lower(trim($claims['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $isNew = false;

        if ($user && $user->hasRole('Suspended User')) {
            return $this->fail('This account has been suspended. Contact support if you think this is a mistake.', 403);
        }

        if (! $user) {
            $user = DB::transaction(function () use ($claims, $email) {
                $name = trim((string) ($claims['name'] ?? ''));

                $user = User::create([
                    'name' => Str::limit($name !== '' ? $name : Str::before($email, '@'), 160, ''),
                    'email' => $email,
                    // Never typed, never shown: the account signs in with
                    // Google until its owner sets a password of their own.
                    'password' => Hash::make(Str::random(64)),
                ]);

                // Same starting role as the registration form.
                $user->assignRole('Free Customer');

                return $user;
            });

            $isNew = true;
        }

        // Google has just proven this address belongs to the person.
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()]);

            if (! $isNew) {
                // The account existed with an address nobody had proven.
                // Anyone else holding a session or "remember me" cookie on
                // it is signed out now that the real owner has shown up.
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->setRememberToken(Str::random(60));
            }

            $user->save();
        }

        if ($isNew) {
            event(new Registered($user));
        }

        // One use per nonce: a token that has signed someone in cannot be
        // replayed. Kept until now on purpose, so that a rejected attempt
        // (unverified email, suspended account) can be retried on the same
        // page without a refresh.
        $request->session()->forget(self::NONCE_KEY);

        Auth::login($user, remember: true);

        // Rotates the session id so a session fixed before login is useless.
        $request->session()->regenerate();

        return response()->json([
            'redirect' => $this->intended($request),
            'new' => $isNew,
        ]);
    }

    /**
     * The page the visitor was trying to reach before being sent to log in,
     * kept to this site only.
     */
    private function intended(Request $request): string
    {
        $url = (string) $request->session()->pull('url.intended', '');

        if ($url !== '' && parse_url($url, PHP_URL_HOST) === $request->getHost()) {
            return $url;
        }

        return route('dashboard');
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
