<?php
	// MODIFIED: Added standalone login handling logic
	session_start();
	if (isset($_SESSION['user'])) {
		header('Location: index.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - Parallel Leaves</title>
	<link rel="stylesheet" href="node_modules/bootstrap-icons/font/bootstrap-icons.css">
	<link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">
<div class="card w-96 bg-base-100 shadow-xl">
	<div class="card-body">
		<h2 class="card-title justify-center text-2xl mb-4" data-i18n="dashboard.login.title">Sign In</h2>
		<form id="login-form" class="space-y-4">
			<div class="form-control">
				<label for="login-username" class="label">
					<span class="label-text" data-i18n="dashboard.login.username">Username</span>
				</label>
				<input type="text" id="login-username" name="username" class="input input-bordered" required>
			</div>
			<div class="form-control">
				<label for="login-password" class="label">
					<span class="label-text" data-i18n="dashboard.login.password">Password</span>
				</label>
				<input type="password" id="login-password" name="password" class="input input-bordered" required>
			</div>
			<p id="login-error-message" class="text-error text-sm hidden"></p>
			<div class="form-control mt-6">
				<button id="login-submit-btn" type="submit" class="btn btn-primary w-full" data-i18n="dashboard.login.signIn">Sign In</button>
			</div>
		</form>
		<div class="text-center text-sm mt-4">
			<span data-i18n="dashboard.login.noAccount">Don't have an account?</span>
			<a id="signup-link" href="register.php" class="link link-primary" data-i18n="dashboard.login.signUp">Sign Up</a>
		</div>
	</div>
</div>
<script src="js/api.js"></script>
<script>
	document.getElementById('login-form').addEventListener('submit', async (e) => {
		e.preventDefault();
		const username = document.getElementById('login-username').value;
		const password = document.getElementById('login-password').value;
		const errorMsg = document.getElementById('login-error-message');

		try {
			const result = await window.api.login({ username, password });
			if (result && result.session) {
				window.location.href = 'index.php';
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
