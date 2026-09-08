@extends('layouts.auth')
@section('title', 'Sign in — Parallel Leaves')
@section('form')
<p class="eyebrow">BACK TO YOUR BOOKS</p><h2>Welcome back.</h2><p>Pick up where your last sentence left off.</p>
@if(session('status'))<div class="form-message" role="status">{{ session('status') }}</div>@endif
@if(session('google_error'))<div class="form-message error" role="alert">{{ is_string(session('google_error')) ? session('google_error') : 'Google sign-in failed. Please try again.' }}</div>@endif
<a href="/login/google" class="button google-button"><i class="bi bi-google" aria-hidden="true"></i> Continue with Google</a><div class="divider">or sign in with your password</div>
<form data-auth-action="/api/auth/login" id="login-form">
<div class="field"><label for="login-username">Username or email</label><input id="login-username" name="username" autocomplete="username" required></div>
<div class="field"><label for="login-password">Password</label><div class="password-field"><input type="password" id="login-password" name="password" autocomplete="current-password" required><button type="button" data-password-toggle="login-password" aria-label="Show or hide password" aria-pressed="false">Show</button></div></div>
<div class="form-links"><a href="/forgot-password">Forgot password?</a></div><div class="form-message error" role="alert" tabindex="-1" hidden></div><button id="login-submit-btn" type="submit" class="button">Sign in ↗</button></form>
<p class="form-footer">New to Parallel Leaves? <a href="/register">Create a free account</a></p>
@endsection
