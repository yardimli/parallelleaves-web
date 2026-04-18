const { ipcMain, shell } = require('electron');
const fetch = require('node-fetch');

/**
 * Registers IPC handlers for authentication.
 * @param {object} sessionManager - The session manager instance.
 */
function registerAuthHandlers(sessionManager) {
	const LOGIN_API_URL = '/parallelleaves-web/sever/login.php';
	const REGISTER_URL = '/parallelleaves-web/sever/register.php';
	
	ipcMain.handle('auth:login', async (event, credentials) => {
		try {
			const response = await fetch(LOGIN_API_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(credentials)
			});
			
			const data = await response.json();
			
			if (response.ok && data.success) {
				const session = {
					token: data.token,
					user: data.user
				};
				sessionManager.setSession(session);
				return { success: true, session: session };
			} else {
				return { success: false, message: data.message || 'Login failed' };
			}
		} catch (error) {
			console.error('Login error:', error);
			return { success: false, message: error.message };
		}
	});
	
	ipcMain.handle('auth:logout', () => {
		sessionManager.clearSession();
		return { success: true };
	});
	
	ipcMain.handle('auth:get-session', () => {
		return sessionManager.getSession();
	});
	
	ipcMain.on('auth:open-register-url', () => {
			shell.openExternal(REGISTER_URL);
	});
}

module.exports = { registerAuthHandlers };
