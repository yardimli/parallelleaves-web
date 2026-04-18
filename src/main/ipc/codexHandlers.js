const { ipcMain, app } = require('electron');
const path = require('path');
const fs = require('fs');
const { htmlToPlainText } = require('../utils.js');
const fetch = require('node-fetch');

let activeCodexJobs = new Map();

/**
 * A generic function to call the server-side codex API.
 * @param {string} action - The specific API action (e.g., 'codex_get_status').
 * @param {object} payload - The data to send.
 * @param {string} token - The user's auth token.
 * @returns {Promise<any>} The JSON response from the server.
 */
async function callCodexApi(action, payload, token) {
	const fullPayload = { ...payload, auth_token: token };
	
	const response = await fetch(`/parallelleaves-web/sever/ai-proxy.php?action=${action}`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(fullPayload),
	});
	
	const responseText = await response.text();
	if (!response.ok) {
		let errorMessage = `Server error: ${response.status}`;
		try {
			const errorJson = JSON.parse(responseText);
			errorMessage = errorJson.error?.message || responseText;
		} catch (e) {
			// Ignore if response is not JSON
		}
		throw new Error(errorMessage);
	}
	
	try {
		return JSON.parse(responseText);
	} catch (e) {
		throw new Error('Invalid JSON response from server.');
	}
}

/**
 * Registers IPC handlers for the server-side Codex functionality.
 * @param {Database.Database} db - The application's database connection.
 * @param {object} sessionManager - The session manager instance.
 * @param {object} windowManager - The window manager instance.
 */
function registerCodexHandlers(db, sessionManager, windowManager) {
	ipcMain.on('codex:start-generation', async (event, bookId) => {
		const sender = event.sender;
		const token = sessionManager.getSession()?.token;
		
		if (!token) {
			sender.send('codex:finished', { status: 'error', message: 'User not authenticated.' });
			return;
		}
		
		if (activeCodexJobs.has(bookId)) {
			console.log(`Codex generation for book ${bookId} is already in progress.`);
			return;
		}
		
		activeCodexJobs.set(bookId, true);
		
		try {
			// 1. Get book languages and current status from server
			sender.send('codex:update', { statusKey: 'editor.codex.status.checking' });
			// MODIFIED: Fetch full status object from the server.
			const serverStatus = await callCodexApi('codex_get_status', { book_id: bookId }, token);
			const { status, processed, total } = serverStatus;
			
			// If the codex is already complete, no need to do anything else.
			if (status === 'complete') {
				sender.send('codex:finished', { status: 'complete' });
				activeCodexJobs.delete(bookId);
				return;
			}
			
			const book = db.prepare('SELECT title, author, source_language, target_language FROM user_books WHERE id = ?').get(bookId);
			if (!book) {
				throw new Error(`Book with ID ${bookId} not found locally.`);
			}
			
			// 2. Get content and split into chunks
			sender.send('codex:update', { statusKey: 'editor.codex.status.preparing' });
			const chapters = db.prepare('SELECT source_content FROM chapters WHERE book_id = ? AND source_content IS NOT NULL AND LENGTH(source_content) > 10').all(bookId);
			if (chapters.length === 0) {
				sender.send('codex:finished', { status: 'complete' });
				activeCodexJobs.delete(bookId);
				return;
			}
			
			const fullText = chapters.map(c => htmlToPlainText(c.source_content)).join('\n');
			const words = fullText.split(/\s+/);
			const chunkSize = 8000; // Approx word count for large contexts
			const chunks = [];
			for (let i = 0; i < words.length; i += chunkSize) {
				chunks.push(words.slice(i, i + chunkSize).join(' '));
			}
			
			if (chunks.length === 0) {
				sender.send('codex:finished', { status: 'complete' });
				activeCodexJobs.delete(bookId);
				return;
			}
			
			// MODIFIED: Logic to determine whether to resume or start a new job.
			let startChunkIndex = 0;
			
			// If status is 'generating' or 'error', we attempt to resume.
			// If the number of local chunks has changed, we restart the job from scratch.
			if ((status === 'generating' || status === 'error') && total === chunks.length) {
				startChunkIndex = processed; // Start from the next unprocessed chunk.
				console.log(`Resuming codex generation for book ${bookId} from chunk ${startChunkIndex}.`);
			} else {
				// Start a fresh job if status is 'none' or if chunk counts mismatch.
				await callCodexApi('codex_start_job', {
					book_id: bookId,
					total_chunks: chunks.length,
					title: book.title,
					author: book.author,
					source_language: book.source_language,
					target_language: book.target_language,
				}, token);
			}
			
			// 4. Process chunks sequentially, starting from the correct index
			// MODIFIED: The loop now starts from `startChunkIndex` to allow resuming.
			for (let i = startChunkIndex; i < chunks.length; i++) {
				if (!activeCodexJobs.has(bookId)) { // Check if job was cancelled
					sender.send('codex:finished', { status: 'cancelled' });
					return;
				}
				
				sender.send('codex:update', { statusKey: 'editor.codex.status.generating', progress: i + 1, total: chunks.length });
				
				await callCodexApi('codex_process_chunk', {
					book_id: bookId,
					chunk_text: chunks[i],
					chunk_index: i,
				}, token);
			}
			
			// 5. Finalize
			await callCodexApi('codex_mark_complete', { book_id: bookId }, token);
			sender.send('codex:finished', { status: 'complete' });
			
		} catch (error) {
			console.error(`Codex generation failed for book ${bookId}:`, error);
			sender.send('codex:finished', { status: 'error', message: error.message });
		} finally {
			activeCodexJobs.delete(bookId);
		}
	});
	
	ipcMain.on('codex:stop-generation', (event, bookId) => {
		if (activeCodexJobs.has(bookId)) {
			activeCodexJobs.delete(bookId);
			console.log(`Codex generation for book ${bookId} cancelled by user.`);
		}
	});
}

module.exports = { registerCodexHandlers };
