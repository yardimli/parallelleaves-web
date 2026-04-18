const { ipcMain, app } = require('electron');
const aiService = require('../../ai/ai.js');
const { htmlToPlainText } = require('../utils.js');
const path = require('path');
const fs = require('fs');
const fetch = require('node-fetch');

/**
 * A generic function to call the translation memory API endpoints.
 * @param {string} action - The specific API action to call (e.g., 'tm_sync_blocks').
 * @param {object} payload - The data to send in the request body.
 * @param {string|null} token - The user's session token.
 * @returns {Promise<any>} The JSON response from the API.
 */
async function callTmApi(action, payload, token) {
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
 * Extracts translation pairs from source and target HTML based on markers.
 * @param {string} sourceHtml - The source HTML content.
 * @param {string} targetHtml - The target HTML content.
 * @returns {Array<object>} An array of {marker, source, target} text pairs.
 */
const extractAllMarkerPairs = (sourceHtml, targetHtml) => {
	if (!sourceHtml || !targetHtml) {
		return [];
	}
	
	const getSegments = (html) => {
		const segments = [];
		const openingMarkerRegex = /\[\[#(\d+)\]\]/g;
		let match;
		
		while ((match = openingMarkerRegex.exec(html)) !== null) {
			const number = parseInt(match[1], 10);
			const openMarkerEndIndex = match.index + match[0].length;
			
			const closingMarkerRegex = new RegExp(`\\{\\{#${number}\\}\\}`);
			const restOfString = html.substring(openMarkerEndIndex);
			const closeMatch = restOfString.match(closingMarkerRegex);
			
			if (closeMatch) {
				const contentEndIndex = openMarkerEndIndex + closeMatch.index;
				const contentHtml = html.substring(openMarkerEndIndex, contentEndIndex);
				const contentWithoutInnerMarkers = contentHtml.replace(/(\[\[#\d+\]\])|(\{\{#\d+\}\})/g, '');
				const plainText = htmlToPlainText(contentWithoutInnerMarkers).trim();
				
				if (plainText) {
					segments.push({ number, text: plainText });
				}
			}
		}
		return segments;
	};
	
	const sourceSegments = getSegments(sourceHtml);
	const targetSegments = getSegments(targetHtml);
	
	const sourceMap = new Map(sourceSegments.map(s => [s.number, s.text]));
	const pairs = [];
	
	for (const targetSegment of targetSegments) {
		if (sourceMap.has(targetSegment.number)) {
			pairs.push({
				marker: targetSegment.number,
				source: sourceMap.get(targetSegment.number),
				target: targetSegment.text
			});
		}
	}
	
	return pairs.sort((a, b) => a.marker - b.marker);
};

/**
 * Checks the server to see which books have a translation memory.
 * @param {string|null} token The user's auth token.
 * @returns {Promise<{success: boolean, bookIds?: number[], message?: string}>}
 */
async function hasValidTranslationMemory(token) {
	try {
		const result = await callTmApi('tm_get_all_with_memory', {}, token);
		return { success: true, bookIds: result.book_ids || [] };
	} catch (error) {
		return { success: false, message: error.message };
	}
}

// Map to store active job runners to prevent multiple concurrent jobs for the same book.
const activeTmJobs = new Map();

/**
 * Registers IPC handlers for the translation memory window functionality.
 * @param {Database.Database} db - The application's database connection.
 * @param {object} sessionManager - The session manager instance.
 * @param {object} windowManager - The window manager instance.
 */
function registerTranslationMemoryHandlers(db, sessionManager, windowManager) {
	ipcMain.handle('translation-memory:generate-in-background', async (event, bookId) => {
		const editorWindow = event.sender.getOwnerBrowserWindow();
		const token = sessionManager.getSession()?.token || null;
		
		if (activeTmJobs.has(bookId)) {
			console.log(`Job for book ${bookId} is already running.`);
			return { success: false, error: 'A generation job for this book is already in progress.' };
		}
		
		const processNextBatch = async (jobId) => {
			if (!activeTmJobs.has(bookId)) {
				console.log(`Job for book ${bookId} was cancelled or finished.`);
				return;
			}
			
			try {
				// Process one block on the server
				await callTmApi('tm_process_job_batch', { job_id: jobId }, token);
				
				// Get the latest status
				const status = await callTmApi('tm_get_job_status', { job_id: jobId }, token);
				
				if (editorWindow && !editorWindow.isDestroyed()) {
					editorWindow.webContents.send('translation-memory:progress-update', {
						processed: status.processed_blocks,
						total: status.total_blocks
					});
				}
				
				// Check for completion or error
				if (status.status === 'complete') {
					if (editorWindow && !editorWindow.isDestroyed()) {
						editorWindow.webContents.send('translation-memory:progress-update', {
							finished: true,
							processedCount: status.processed_blocks
						});
					}
					activeTmJobs.delete(bookId); // Job finished, remove from active list
				} else if (status.status === 'error') {
					throw new Error(status.error_message || 'Unknown server error during job processing.');
				} else {
					// Schedule the next batch
					setTimeout(() => processNextBatch(jobId), 100); // Small delay
				}
			} catch (error) {
				console.error(`Error in TM job for book ${bookId}:`, error);
				if (editorWindow && !editorWindow.isDestroyed()) {
					editorWindow.webContents.send('translation-memory:progress-update', {
						error: true,
						message: error.message
					});
				}
				activeTmJobs.delete(bookId); // Job failed, remove from active list
			}
		};
		
		try {
			activeTmJobs.set(bookId, true); // Mark job as active
			
			// Step 1: Sync local content
			editorWindow.webContents.send('translation-memory:progress-update', { message: 'Syncing book content...' });
			// MODIFIED: Fetch title and author along with languages
			const book = db.prepare('SELECT title, author, source_language, target_language FROM user_books WHERE id = ?').get(bookId);
			if (!book) throw new Error('Book not found locally.');
			
			const chapters = db.prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?').all(bookId);
			const combinedSource = chapters.map(c => c.source_content || '').join('');
			const combinedTarget = chapters.map(c => c.target_content || '').join('');
			const allPairs = extractAllMarkerPairs(combinedSource, combinedTarget);
			
			// MODIFIED: Send title and author in the sync payload
			await callTmApi('tm_sync_blocks', {
				book_id: bookId,
				title: book.title,
				author: book.author,
				source_language: book.source_language,
				target_language: book.target_language,
				pairs: allPairs
			}, token);
			
			// Step 2: Start the job on the server
			const jobResult = await callTmApi('tm_start_generation_job', { book_id: bookId }, token);
			
			if (jobResult.job_id) {
				editorWindow.webContents.send('translation-memory:progress-update', {
					processed: 0,
					total: jobResult.total_blocks
				});
				// Kick off the processing loop
				processNextBatch(jobResult.job_id);
				return { success: true };
			} else {
				// No new blocks to process
				editorWindow.webContents.send('translation-memory:progress-update', {
					finished: true,
					processedCount: 0
				});
				activeTmJobs.delete(bookId);
				return { success: true, processedCount: 0 };
			}
			
		} catch (error) {
			console.error('Background translation memory generation failed:', error);
			if (editorWindow && !editorWindow.isDestroyed()) {
				editorWindow.webContents.send('translation-memory:progress-update', {
					error: true,
					message: error.message
				});
			}
			activeTmJobs.delete(bookId); // Ensure job is cleared on initial failure
			return { success: false, error: error.message };
		}
	});
}

module.exports = { registerTranslationMemoryHandlers, hasValidTranslationMemory, extractAllMarkerPairs };
