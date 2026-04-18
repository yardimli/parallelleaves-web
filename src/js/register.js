document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('register-form');
	const alertBox = document.getElementById('register-alert');
	const alertMsg = document.getElementById('register-alert-msg');
	const registerBtn = document.getElementById('register-btn');
	
	form.addEventListener('submit', async (e) => {
		e.preventDefault();
		registerBtn.disabled = true;
		alertBox.classList.add('hidden');
		
		const username = form.elements.username.value.trim();
		const password = form.elements.password.value;
		
		try {
			const result = await window.api.register({ username, password });
			if (result.success) {
				alertBox.className = 'alert alert-success my-4';
				alertMsg.textContent = 'Registration successful! You can now log in.';
				form.reset();
			} else {
				alertBox.className = 'alert alert-error my-4';
				alertMsg.textContent = result.message || 'Registration failed.';
			}
		} catch (error) {
			alertBox.className = 'alert alert-error my-4';
			alertMsg.textContent = error.message;
		} finally {
			alertBox.classList.remove('hidden');
			registerBtn.disabled = false;
		}
	});
});
