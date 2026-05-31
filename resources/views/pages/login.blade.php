<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - Parallel Leaves</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body class="bg-base-200 min-h-screen flex flex-col items-center justify-center p-4">

<!-- MODIFIED: Added uniform brand header containing the logo, title, and current version -->
<div class="text-center mb-6">
	<img src="/assets/android-chrome-192x192.png" alt="Parallel Leaves Brand Logo" class="w-16 h-16 rounded-xl mx-auto mb-2 shadow-md">
	<h1 class="text-3xl font-bold">Parallel Leaves</h1>
	<p class="text-xs text-base-content/60 mt-1">v{{ env('APP_VERSION', '0.1') }}</p>
</div>

<div class="card w-96 bg-base-100 shadow-xl">
	<div class="card-body">
		<h2 class="card-title justify-center text-2xl mb-4">{{ $tr('dashboard.login.title', 'Sign In') }}</h2>
		<a href="/login/google" class="btn btn-outline w-full mb-4">
			<i class="bi bi-google"></i>
			<span>Sign in with Google</span>
		</a>
		<div class="divider my-2">or</div>
		<form id="login-form" class="space-y-4">
			<div class="form-control">
				<label for="login-username" class="label">
					<span class="label-text">{{ $tr('dashboard.login.username', 'Username') }}</span>
				</label>
				<input type="text" id="login-username" name="username" class="input input-bordered" required>
			</div>
			<div class="form-control">
				<label for="login-password" class="label">
					<span class="label-text">{{ $tr('dashboard.login.password', 'Password') }}</span>
				</label>
				<input type="password" id="login-password" name="password" class="input input-bordered" required>
			</div>
			<p id="login-error-message" class="text-error text-sm hidden"></p>
			<div class="form-control mt-6">
				<button id="login-submit-btn" type="submit" class="btn btn-primary w-full">{{ $tr('dashboard.login.signIn', 'Sign In') }}</button>
			</div>
		</form>
		<div class="text-center text-sm mt-4">
			<span>{{ $tr('dashboard.login.noAccount', 'Don\'t have an account?') }}</span>
			<a id="signup-link" href="/register" class="link link-primary">{{ $tr('dashboard.login.signUp', 'Sign Up') }}</a>
		</div>
	</div>
</div>
<script src="/js/api.js"></script>
<script>
	// MODIFIED: Restyled script structure according to StandardJS with semicolons
	if (@json(session('google_error', false))) {
		const errorMsg = document.getElementById('login-error-message');
		errorMsg.textContent = 'Google sign in failed. Please try again.';
		errorMsg.classList.remove('hidden');
	}
	
	document.getElementById('login-form').addEventListener('submit', async (e) => {
		e.preventDefault();
		const username = document.getElementById('login-username').value;
		const password = document.getElementById('login-password').value;
		const errorMsg = document.getElementById('login-error-message');
		
		try {
			const result = await window.api.login({username, password});
			if (result && result.session) {
				window.location.href = '/dashboard';
			} else {
				errorMsg.textContent = 'Invalid credentials';
				errorMsg.classList.remove('hidden');
			}
		} catch (err) {
			errorMsg.textContent = err.message;
			errorMsg.classList.remove('hidden');
		}
	});
</script>
</body>
</html>
