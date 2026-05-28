import { t } from './i18n.js';

/**
 * Handles the entire process of exporting a book to a DOCX file.
 * @param {number} bookId - The ID of the book to export.
 */
export async function exportBook(bookId) {
	try {
		// 1. Fetch all necessary data from the main process.
		const result = await window.api.getBookForExport(bookId);
		if (!result.success) {
			throw new Error(result.message);
		}
		const book = result.data;
		
		// 2. Construct a single HTML string from the book data.
		let htmlContent = `<h1>${book.title}</h1>`;
		if (book.author) {
			htmlContent += `<p><em>by ${book.author}</em></p>`;
		}
		
		book.chapters.forEach(chapter => {
			// Add Chapter breaks using a page break and heading.
			htmlContent += `<br pageBreakBefore="true" /><h3>${chapter.title}</h3>`;
			
			const content = chapter.target_content || '<p><em>(No content)</em></p>';
			// Clean markers from the final output.
			const cleanedContent = content.replace(/(\[\[#\d+\]\])|(\{\{#\d+\}\})/g, '');
			htmlContent += cleanedContent;
		});
		
		// 3. Send the constructed HTML, target language, and localized dialog strings to the main process.
		const exportResult = await window.api.exportBookToDocx({
			title: book.title,
			htmlContent: htmlContent,
			targetLanguage: book.target_language,
			dialogStrings: {
				title: t('export.exportDialogTitle'),
				message: t('export.exportDialogMessage', { title: book.title }),
				detail: t('export.exportDialogDetail'), // {filePath} is a placeholder for the main process
				openFolder: t('export.exportDialogOpenFolder'),
				ok: t('export.exportDialogOK'),
			},
		});
		
		if (!exportResult.success) {
			if (exportResult.message !== 'Export cancelled by user.') {
				throw new Error(exportResult.message);
			}
		}
		
	} catch (error) {
		console.error('Export failed:', error);
		window.showAlert(
			t('export.exportErrorMessage', { message: error.message }),
			t('export.exportErrorTitle')
		);
	}
}
