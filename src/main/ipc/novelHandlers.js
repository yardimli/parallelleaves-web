const { ipcMain, BrowserWindow, dialog, shell } = require('electron');
const path = require('path');
const fs = require('fs');
const HTMLtoDOCX = require('html-to-docx');
const imageHandler = require('../../utils/image-handler.js');
const { countWordsInHtml, htmlToPlainText } = require('../utils.js');
const { mapLanguageToIsoCode } = require('../../js/languages.js');
const { hasValidTranslationMemory, extractAllMarkerPairs } = require('./translationMemoryHandlers.js');

function toRoman(num) {
	const roman = { M: 1000, CM: 900, D: 500, CD: 400, C: 100, XC: 90, L: 50, XL: 40, X: 10, IX: 9, V: 5, IV: 4, I: 1 };
	let str = '';
	for (let i of Object.keys(roman)) {
		let q = Math.floor(num / roman[i]);
		num -= q * roman[i];
		str += i.repeat(q);
	}
	return str;
}

/**
 * Registers IPC handlers for book-related functionality.
 * @param {Database.Database} db - The application's database connection.
 * @param {object} sessionManager - The session manager instance.
 * @param {object} windowManager - The window manager instance.
 */
function registerBookHandlers(db, sessionManager, windowManager) {
	ipcMain.handle('books:getAllWithCovers', (event) => {
		const stmt = db.prepare(`
            SELECT
                n.*,
                i.image_local_path as cover_path,
                (SELECT COUNT(id) FROM chapters WHERE book_id = n.id) as chapter_count
            FROM user_books n
            LEFT JOIN (
                SELECT book_id, image_local_path, ROW_NUMBER() OVER(PARTITION BY book_id ORDER BY created_at DESC) as rn
                FROM images
            ) i ON n.id = i.book_id AND i.rn = 1
            ORDER BY n.updated_at DESC
        `);
		const books = stmt.all();
		
		for (const book of books) {
			const chapters = db.prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?').all(book.id);
			
			book.source_word_count = chapters.reduce((sum, ch) => sum + countWordsInHtml(ch.source_content), 0);
			book.target_word_count = chapters.reduce((sum, ch) => sum + countWordsInHtml(ch.target_content), 0);
			
			if (book.cover_path) {
				book.cover_path = `/images/${book.cover_path.replace(/\\/g, '/')}`;
			}
		}
		
		return books;
	});
	
	ipcMain.handle('books:getAllWithTranslationMemory', async (event) => {
		try {
			const allBooks = db.prepare('SELECT id, title FROM user_books ORDER BY title ASC').all();
			const booksWithTmResult = await hasValidTranslationMemory(sessionManager.getSession()?.token);
			
			if (!booksWithTmResult.success) {
				console.error('Failed to get books with TM from server:', booksWithTmResult.message);
				return [];
			}
			
			const tmBookIds = new Set(booksWithTmResult.bookIds);
			return allBooks.filter(book => tmBookIds.has(book.id));
		} catch (error) {
			console.error('Failed to get books with translation memory:', error);
			return [];
		}
	});
	
	ipcMain.handle('books:createBlank', (event, { title, source_language, target_language }) => {
		try {
			const session = sessionManager.getSession();
			if (!session || !session.user) {
				return { success: false, message: 'User not authenticated.' };
			}
			const userId = session.user.id;
			
			const bookResult = db.prepare(
				'INSERT into user_books (user_id, title, author, source_language, target_language) VALUES (?, ?, ?, ?, ?)'
			).run(userId, title, '', source_language, target_language);
			
			const bookId = bookResult.lastInsertRowid;
			
			const insertChapter = db.prepare('INSERT INTO chapters (book_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)');
			
			db.transaction(() => {
				for (let j = 1; j <= 10; j++) { // 10 Chapters
					insertChapter.run(bookId, `Chapter ${j}`, j, '<p></p>', '<p></p>');
				}
			})();
			
			return { success: true, bookId };
		} catch (error) {
			console.error('Failed to create blank book:', error);
			return { success: false, message: error.message };
		}
	});
	
	ipcMain.handle('books:getOne', (event, bookId) => {
		const book = db.prepare('SELECT id, title, source_language, target_language, rephrase_settings, translate_settings FROM user_books WHERE id = ?').get(bookId);
		if (!book) return null;
		
		book.chapters = db.prepare('SELECT * FROM chapters WHERE book_id = ? ORDER BY chapter_order').all(bookId);
		
		return book;
	});
	
	ipcMain.handle('books:getForExport', (event, bookId) => {
		try {
			const book = db.prepare('SELECT id, title, author, target_language FROM user_books WHERE id = ?').get(bookId);
			if (!book) throw new Error('Book not found.');
			
			book.chapters = db.prepare('SELECT id, title, target_content FROM chapters WHERE book_id = ? ORDER BY chapter_order').all(bookId);
			
			return { success: true, data: book };
		} catch (error) {
			console.error(`Failed to get book for export (ID: ${bookId}):`, error);
			return { success: false, message: error.message };
		}
	});
	
	ipcMain.handle('books:exportToDocx', async (event, { title, htmlContent, targetLanguage, dialogStrings }) => {
		try {
			const langCode = mapLanguageToIsoCode(targetLanguage || 'English');
			
			const fileBuffer = await HTMLtoDOCX(htmlContent, null, {
				table: { row: { cantSplit: true } },
				footer: true,
				pageNumber: true,
				lang: langCode
			});
			
			// Save to the server's downloads directory
			const filename = `${title.replace(/[^a-z0-9]/gi, '_')}_${Date.now()}.docx`;
			const downloadsDir = path.join(require('electron').app.getPath('userData'), 'downloads');
			if (!fs.existsSync(downloadsDir)) fs.mkdirSync(downloadsDir, { recursive: true });
			
			const filePath = path.join(downloadsDir, filename);
			fs.writeFileSync(filePath, fileBuffer);
			
			// Return the URL so the frontend can trigger the download
			return { success: true, downloadUrl: `/downloads/${filename}`, filename };
		} catch (error) {
			console.error('Failed to convert HTML to DOCX:', error);
			return { success: false, message: error.message };
		}
	});
	
	ipcMain.handle('books:updatePromptSettings', (event, { bookId, promptType, settings }) => {
		const allowedTypes = ['rephrase', 'translate'];
		if (!allowedTypes.includes(promptType)) {
			return { success: false, message: 'Invalid prompt type.' };
		}
		const settingsJson = JSON.stringify(settings);
		const fieldName = `${promptType}_settings`;
		
		try {
			db.prepare(`UPDATE user_books SET ${fieldName} = ? WHERE id = ?`).run(settingsJson, bookId);
			return { success: true };
		} catch (error) {
			console.error(`Failed to update prompt settings for book ${bookId}:`, error);
			throw new Error('Failed to update prompt settings.');
		}
	});
	
	ipcMain.handle('books:getFullManuscript', (event, bookId) => {
		try {
			const book = db.prepare('SELECT * FROM user_books WHERE id = ?').get(bookId);
			if (!book) return { id: bookId, title: 'Not Found', chapters: [] };
			
			book.chapters = db.prepare('SELECT id, title, source_content, target_content, chapter_order FROM chapters WHERE book_id = ? ORDER BY chapter_order').all(bookId);
			for (const chapter of book.chapters) {
				chapter.source_word_count = countWordsInHtml(chapter.source_content);
				chapter.target_word_count = countWordsInHtml(chapter.target_content);
			}
			return book;
		} catch (error) {
			console.error(`Error in getFullManuscript for bookId ${bookId}:`, error);
			return { id: bookId, title: 'Error Loading', chapters: [] };
		}
	});
	
	ipcMain.handle('books:getAllBookContent', (event, bookId) => {
		try {
			const chapters = db.prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?').all(bookId);
			const combinedContent = chapters.map(c => (c.source_content || '') + (c.target_content || '')).join('');
			return { success: true, combinedHtml: combinedContent };
		} catch (error) {
			console.error(`Failed to get all content for book ${bookId}:`, error);
			return { success: false, message: 'Failed to retrieve book content.' };
		}
	});
	
	ipcMain.handle('books:updateProseSettings', (event, { bookId, source_language, target_language }) => {
		try {
			db.prepare('UPDATE user_books SET source_language = ?, target_language = ? WHERE id = ?').run(source_language, target_language, bookId);
			return { success: true };
		} catch (error) {
			console.error('Failed to update language settings:', error);
			throw new Error('Failed to update language settings.');
		}
	});
	
	ipcMain.handle('books:updateMeta', (event, { bookId, title, author }) => {
		try {
			db.prepare('UPDATE user_books SET title = ?, author = ? WHERE id = ?').run(title, author, bookId);
			return { success: true };
		} catch (error) {
			console.error('Failed to update book meta:', error);
			throw new Error('Failed to update book metadata.');
		}
	});
	
	ipcMain.handle('books:updateBookCover', async (event, { bookId, coverInfo }) => {
		let localPath;
		let imageType = 'unknown';
		
		if (coverInfo.type === 'remote') {
			localPath = await imageHandler.storeImageFromUrl(coverInfo.data, bookId, 'cover');
			imageType = 'generated';
		} else if (coverInfo.type === 'local') {
			const paths = await imageHandler.storeImageFromPath(coverInfo.data, bookId, 'cover-upload');
			localPath = paths.original_path;
			imageType = 'upload';
		}
		
		if (!localPath) {
			throw new Error('Failed to store the new cover image.');
		}
		
		db.transaction(() => {
			const oldImage = db.prepare('SELECT * FROM images WHERE book_id = ?').get(bookId);
			if (oldImage && oldImage.image_local_path) {
				const oldFullPath = path.join(imageHandler.IMAGES_DIR, oldImage.image_local_path);
				if (fs.existsSync(oldFullPath)) fs.unlinkSync(oldFullPath);
			}
			db.prepare('DELETE FROM images WHERE book_id = ?').run(bookId);
			
			db.prepare('INSERT INTO images (user_id, book_id, image_local_path, thumbnail_local_path, image_type) VALUES (?, ?, ?, ?, ?)')
				.run(1, bookId, localPath, localPath, imageType);
		})();
		
		const absolutePath = path.join(imageHandler.IMAGES_DIR, localPath);
		BrowserWindow.getAllWindows().forEach(win => {
			win.webContents.send('books:cover-updated', { bookId, imagePath: absolutePath });
		});
		
		return { success: true };
	});
	
	ipcMain.handle('books:delete', (event, bookId) => {
		db.transaction(() => {
			const imagesToDelete = db.prepare('SELECT image_local_path, thumbnail_local_path FROM images WHERE book_id = ?').all(bookId);
			
			for (const image of imagesToDelete) {
				if (image.image_local_path) {
					const fullPath = path.join(imageHandler.IMAGES_DIR, image.image_local_path);
					if (fs.existsSync(fullPath)) fs.unlinkSync(fullPath);
				}
				if (image.thumbnail_local_path) {
					const thumbPath = path.join(imageHandler.IMAGES_DIR, image.thumbnail_local_path);
					if (fs.existsSync(thumbPath)) fs.unlinkSync(thumbPath);
				}
			}
			
			db.prepare('DELETE FROM user_books WHERE id = ?').run(bookId);
		})();
		
		return { success: true };
	});
	
	ipcMain.on('books:openEditor', (event, bookId) => {
		windowManager.createChapterEditorWindow({ bookId, chapterId: null });
	});
	
}

module.exports = { registerBookHandlers };
