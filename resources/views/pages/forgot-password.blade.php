@extends('layouts.auth')
@section('title', 'Reset your password — Parallel Leaves')
@section('form')
<p class="eyebrow">A WAY BACK IN</p><h2>Forgot your password?</h2><p>Enter your account email and we'll send a reset link. Links expire after {{ config('auth.passwords.users.expire') }} minutes.</p>
@if(session('status'))<div class="form-message" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="form-message error" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" data-submit-once>@csrf
<div class="field"><label for="email">Email address</label><input type="email" name="email" id="email" autocomplete="email" required value="{{ old('email') }}"></div><button class="button" type="submit">Send reset link ↗</button></form>
<p class="form-footer"><a href="/login">Back to sign in</a></p><p class="legal-note">Older account without an email address? <a href="mailto:{{ config('public.support_email') }}">Contact support</a>. If you use Google, you can still sign in with Google.</p>
@endsection
