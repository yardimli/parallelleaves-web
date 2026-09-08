@extends('layouts.auth')
@section('title', 'Choose a new password — Parallel Leaves')
@section('form')
<p class="eyebrow">A FRESH START</p><h2>Choose a new password.</h2><p>Use a unique password with at least 8 characters.</p>
@if($errors->any())<div class="form-message error" role="alert">{{ $errors->first() }} <a href="/forgot-password">Request a new link</a></div>@endif
<form method="POST" action="{{ route('password.update') }}" data-submit-once>@csrf<input type="hidden" name="token" value="{{ $token }}">
<div class="field"><label for="email">Account email</label><input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', $email) }}"></div>
<div class="field"><label for="password">New password</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"><button type="button" data-password-toggle="password" aria-label="Show or hide new password" aria-pressed="false">Show</button></div></div>
<div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8"></div><button type="submit" class="button">Update password ↗</button></form><p class="form-footer"><a href="/login">Back to sign in</a></p>
@endsection
