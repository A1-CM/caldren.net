@extends('layouts.site')
@section('title', 'Set up your workspace — Caldren')
@section('content')
<section class="auth-shell container">
    <div class="auth-intro">
        <span class="eyebrow">ONE LAST STEP</span>
        <h1>Your energy workspace starts here.</h1>
        <p>Continue with {{ $providerName }} to set up your Site trial. You’ll have 30 days to explore, with no card required.</p>
    </div>
    <div class="form-card auth-card oauth-complete-card">
        <span class="eyebrow">SITE PLAN / 30-DAY TRIAL</span>
        <h2>Set up your organization</h2>
        <p class="oauth-complete-note">Signed in as {{ $profileName }}. Your email was verified by {{ $providerName }}.</p>
        @if ($errors->any())
            <p class="error-message" role="alert">{{ $errors->first() }}</p>
        @endif
        <form method="post" action="{{ route('oauth.finish') }}">
            @csrf
            <label for="oauth-organization">Organization name
                <input id="oauth-organization" name="organization" value="{{ old('organization') }}" maxlength="160" required autocomplete="organization">
            </label>
            @include('components.turnstile')
            <button type="submit" class="button full">Create Site trial</button>
        </form>
    </div>
</section>
@endsection
