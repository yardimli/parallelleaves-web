@extends('layouts.auth')
@section('title', 'Create an account — Parallel Leaves')
@section('form')
<p class="eyebrow">YOUR NEXT CHAPTER</p><h2>Make room for your words.</h2><p>All current features are free. No credit card needed.</p>
<a href="/login/google" class="button google-button"><i class="bi bi-google" aria-hidden="true"></i> Continue with Google</a><div class="divider">or create an account with email</div>
<form data-auth-action="/api/auth/register" id="register-form">
<div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" maxlength="50" required></div>
<div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" autocomplete="email" maxlength="255" required aria-describedby="email-help"><small id="email-help">Used for account access and password recovery.</small></div>
<div class="field"><label for="password">Password</label><div class="password-field"><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required aria-describedby="password-help"><button type="button" data-password-toggle="password" aria-label="Show or hide password" aria-pressed="false">Show</button></div><small id="password-help">At least 8 characters. Use a unique password.</small></div>
<div class="form-message error" role="alert" tabindex="-1" hidden></div><button type="submit" class="button" id="register-btn">Create free account ↗</button></form>
<p class="legal-note">By creating an account, including through Google, you agree to the <a href="/terms">Terms & conditions</a>. Read our <a href="/privacy">Privacy notice</a> to learn how we handle your data.</p><p class="legal-note">App access is free today. AI provider usage through your own key may cost extra.</p><p class="form-footer">Already have an account? <a href="/login">Sign in</a></p>
@endsection
