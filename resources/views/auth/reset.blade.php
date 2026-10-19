@extends('layouts.site')
@section('title','New password — Caldren')
@section('content')<section class="auth-shell container"><div class="auth-intro"><span class="eyebrow">ACCOUNT ACCESS</span><h1>Choose a new password.</h1><p>Use a strong password of at least 12 characters.</p></div><div class="form-card auth-card"><h2>New password</h2><form method="post" action="{{ route('password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ old('email',$email) }}" required></label><label>Password<input type="password" name="password" minlength="12" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="12" required></label>@if($errors->any())<p class="error-message">{{ $errors->first() }}</p>@endif
<button class="button full" type="submit">Reset password</button></form></div></section>@endsection
