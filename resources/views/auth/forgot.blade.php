@extends('layouts.auth')
@section('title','Reset password — Caldren')
@section('content')<section class="auth-shell container"><div class="auth-intro"><span class="eyebrow">ACCOUNT ACCESS</span><h1>Get back to your workspace.</h1><p>We will email a password reset link if we have an account for that address.</p></div><div class="form-card auth-card"><h2>Reset your password</h2><form method="post" action="{{ route('password.email') }}">@csrf
<label>Email address<input type="email" name="email" value="{{ old('email') }}" required></label>@if($errors->any())<p class="error-message">{{ $errors->first() }}</p>@endif
<button class="button full" type="submit">Send reset link</button></form><p class="form-note"><a href="{{ route('login') }}">Back to sign in</a></p></div></section>@endsection
