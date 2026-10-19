<div class="turnstile-check" aria-label="Security verification">
    <span class="turnstile-label">Security check</span>
    @if (config('services.turnstile.site_key'))
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
        @once
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endonce
    @else
        <p class="turnstile-unavailable" role="status">Security verification is not configured. This form is currently unavailable.</p>
    @endif
    @error('turnstile', $errorBag ?? 'default')
        <small class="turnstile-error" role="alert">{{ $message }}</small>
    @enderror
</div>
