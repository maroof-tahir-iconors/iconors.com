{{--
    "Continue with Google", shared by the log-in and sign-up pages.

    Google Identity Services draws the button and hands the browser an ID
    token; public/ico-ext/js/google-signin.js posts that token to
    auth.google, where it is verified before anyone is signed in.

    Only the public client ID appears here. There is no client secret in
    this flow at all.

    The nonce binds the token to this visitor's session. It stays the same
    for the whole session (so two open tabs both work) and is replaced
    after each successful sign-in.

    Renders nothing when GOOGLE_CLIENT_ID is not set, so the page simply
    falls back to email and password.
--}}
@php
    $googleClientId = (string) config('services.google.client_id');
    $googleContext = ($context ?? 'signin') === 'signup' ? 'signup' : 'signin';
@endphp

@if ($googleClientId !== '')
    @php
        $nonceKey = \App\Http\Controllers\Auth\GoogleAuthController::NONCE_KEY;
        $googleNonce = session($nonceKey);

        if (! is_string($googleNonce) || $googleNonce === '') {
            $googleNonce = \Illuminate\Support\Str::random(40);
            session()->put($nonceKey, $googleNonce);
        }
    @endphp

    <div class="ico-google" data-ico-google
         data-client-id="{{ $googleClientId }}"
         data-nonce="{{ $googleNonce }}"
         data-context="{{ $googleContext }}"
         data-endpoint="{{ route('auth.google') }}">

        <div class="ico-google__slot" data-ico-google-slot>
            {{-- Same size as Google's button, so nothing jumps when it
                 arrives. Not clickable: it only holds the space. --}}
            <div class="ico-google__placeholder" data-ico-google-placeholder aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                </svg>
                <span>Continue with Google</span>
            </div>
        </div>

        <div class="ico-google__busy" hidden data-ico-google-busy role="status" aria-live="polite">
            <span class="ico-google__spin" aria-hidden="true"></span>
            <span data-ico-google-busy-text>Signing you in…</span>
        </div>

        <p class="ico-google__error" role="alert" hidden data-ico-google-error></p>
    </div>

    <div class="ico-auth-divider" role="separator">
        <span>or {{ $googleContext === 'signup' ? 'sign up' : 'log in' }} with email</span>
    </div>

    @once
        @push('scripts')
            <script defer src="{{ \App\Services\Tools\ToolRegistry::asset('js/google-signin.js') }}"></script>
        @endpush
    @endonce
@endif
