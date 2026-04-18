// MUST BE FIRST: Intercept require('electron') to use our mock
const mockElectron = require('./mock-electron.js');
const Module = require('module');
const originalRequire = Module.prototype.require;
Module.prototype.require = function(request) {
	if (request === 'electron') return mockElectron;
	return originalRequire.apply(this, arguments);
};

const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const cors = require('cors');

const { initializeDatabase } = require('./src/database/database.js');
const sessionManager = require('./src/main/sessionManager.js');
const { registerIpcHandlers } = require('./src/main/ipc/index.js');
const autoBackupManager = require('./src/main/autoBackupManager.js');

const app = express();
app.use(cors());
app.use(express.json({ limit: '50mb' }));

// Static file serving
app.use(express.static('public'));
app.use('/dist', express.static('dist'));
app.use('/src', express.static('src'));
app.use('/node_modules', express.static('node_modules'));

// Serve User Data directories
const userDataPath = path.join(__dirname, 'userData');
const imagesDir = path.join(userDataPath, 'images');
const downloadsDir = path.join(userDataPath, 'downloads');
const tempDir = path.join(userDataPath, 'temp');

[imagesDir, downloadsDir, tempDir].forEach(dir => {
	if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

app.use('/images', express.static(imagesDir));
app.use('/downloads', express.static(downloadsDir));
app.use('/temp', express.static(tempDir));

// --- MODIFIED: Configure Multer to preserve the original file extension ---
const storage = multer.diskStorage({
	destination: function (req, file, cb) {
		cb(null, tempDir);
	},
	filename: function (req, file, cb) {
		// Prepend a timestamp to the original name to avoid collisions,
		// while keeping the original extension (.txt, .docx) intact.
		cb(null, Date.now() + '-' + file.originalname);
	}
});
const upload = multer({ storage: storage });

// File Upload Endpoint
app.post('/api/upload-temp', upload.single('file'), (req, res) => {
	res.json({ filePath: req.file.path, url: `/temp/${req.file.filename}` });
});
// --------------------------------------------------------------------------

// Server-Sent Events (SSE) Endpoint for real-time updates
app.get('/api/events', (req, res) => {
	res.setHeader('Content-Type', 'text/event-stream');
	res.setHeader('Cache-Control', 'no-cache');
	res.setHeader('Connection', 'keep-alive');
	mockElectron._addSseClient(res);
});

// RPC Endpoint to route to IPC handlers
app.post('/api/rpc', mockElectron._router);

// Initialize Backend Core
const db = initializeDatabase();
sessionManager.loadSession();

// Dummy window manager since the browser handles routing now
const dummyWindowManager = {
	createSplashWindow: () => {},
	createMainWindow: () => {},
	createChapterEditorWindow: () => {},
	createImportWindow: () => {},
	createChatWindow: () => {},
	closeSplashAndShowMain: () => {},
	// Added missing getters
	getMainWindow: () => mockElectron.BrowserWindow.getAllWindows()[0],
	getImportWindow: () => mockElectron.BrowserWindow.getAllWindows()[0]
};

registerIpcHandlers(db, sessionManager, dummyWindowManager);
autoBackupManager.initialize(db);

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
	console.log(`Parallel Leaves Web Server running at http://localhost:${PORT}`);
	console.log(`Open http://localhost:${PORT}/splash.html to start.`);
});
