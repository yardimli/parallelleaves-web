const path = require('path');
const fs = require('fs');

const userDataPath = path.join(__dirname, 'userData');
if (!fs.existsSync(userDataPath)) fs.mkdirSync(userDataPath, { recursive: true });

const handlers = {};
const listeners = {};
let sseClients = [];

module.exports = {
	app: {
		getPath: (name) => {
			if (name === 'documents') return path.join(userDataPath, 'documents');
			return userDataPath;
		},
		quit: () => process.exit(0),
		isPackaged: false
	},
	ipcMain: {
		handle: (channel, callback) => { handlers[channel] = callback; },
		on: (channel, callback) => { listeners[channel] = callback; }
	},
	dialog: {
		showSaveDialog: async () => ({ canceled: true }),
		showOpenDialog: async () => ({ canceled: true }),
		showMessageBox: async () => ({ response: 0 })
	},
	shell: {
		showItemInFolder: () => {},
		openExternal: () => {}
	},
	BrowserWindow: {
		getAllWindows: () => [{
			isDestroyed: () => false, // Added
			close: () => {},          // Added
			webContents: {
				send: (channel, data) => {
					sseClients.forEach(client => client.write(`event: ${channel}\ndata: ${JSON.stringify(data)}\n\n`));
				}
			}
		}]
	},
	// Custom router to handle incoming HTTP requests and map them to IPC handlers
	_router: async (req, res) => {
		const { channel, args } = req.body;
		const event = {
			sender: {
				send: (ch, d) => {
					sseClients.forEach(client => client.write(`event: ${ch}\ndata: ${JSON.stringify(d)}\n\n`));
				},
				getOwnerBrowserWindow: () => module.exports.BrowserWindow.getAllWindows()[0],
				session: {
					availableSpellCheckerLanguages: ['en-US'],
					getSpellCheckerLanguages: () => ['en-US'],
					setSpellCheckerLanguages: () => {}
				}
			}
		};
		
		try {
			if (handlers[channel]) {
				const result = await handlers[channel](event, ...(args || []));
				res.json({ success: true, data: result });
			} else if (listeners[channel]) {
				listeners[channel](event, ...(args || []));
				res.json({ success: true });
			} else {
				res.status(404).json({ success: false, message: `Channel ${channel} not found` });
			}
		} catch (error) {
			console.error(`Error in channel ${channel}:`, error);
			res.status(500).json({ success: false, message: error.message });
		}
	},
	_addSseClient: (client) => {
		sseClients.push(client);
		client.on('close', () => {
			sseClients = sseClients.filter(c => c !== client);
		});
	}
};
