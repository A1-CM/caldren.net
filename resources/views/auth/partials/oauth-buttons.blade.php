@php
    $googleOAuthConfigured = filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    $githubOAuthConfigured = filled(config('services.github.client_id')) && filled(config('services.github.client_secret'));
@endphp
@if ($googleOAuthConfigured || $githubOAuthConfigured)
    <section class="oauth-options" aria-label="Sign in with a provider">
        <div class="oauth-divider"><span>OR CONTINUE WITH</span></div>
        @if (session('oauth_error'))
            <p class="oauth-message" role="alert">{{ session('oauth_error') }}</p>
        @endif
        <form method="post" action="{{ route('oauth.start') }}">
            @csrf
            <input type="hidden" name="origin" value="{{ $oauthOrigin }}">
            @if (! empty($inviteToken))
                <input type="hidden" name="invite" value="{{ $inviteToken }}">
            @endif
            <div class="oauth-provider-grid">
                @if ($googleOAuthConfigured)
                    <button class="oauth-provider-button" type="submit" name="provider" value="google"><i class="fa-brands fa-google" aria-hidden="true"></i><span>Continue with Google</span></button>
                @endif
                @if ($githubOAuthConfigured)
                    <button class="oauth-provider-button" type="submit" name="provider" value="github"><i class="fa-brands fa-github" aria-hidden="true"></i><span>Continue with GitHub</span></button>
                @endif
            </div>
        </form>
    </section>
@endif
