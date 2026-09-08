/**
 * Adapts an inline chapter command endpoint to the toolbar and prompt interface.
 * The exported name and type retain the legacy interface contract; no iframe is used.
 */
export const createIframeEditorInterface = (contentWindow) => {
	const post = (type, payload) => contentWindow.postMessage({type, payload}, window.location.origin);
	
	return {
		type: 'iframe',
		// getting the current selection from the target editor to use as an insertion point.
		getSelectionInfo: (action) => new Promise((resolve) => {
			const listener = ({detail: event}) => {
				if (event.source === contentWindow && event.data.type === 'selectionResponse') {
					window.removeEventListener('chapter-editor-message', listener);
					resolve(event.data.payload);
				}
			};
			window.addEventListener('chapter-editor-message', listener);
			
			// Both actions now just need the current selection state from the editor.
			post('prepareForRephrase', {isRephrase: action === 'rephrase'});
		}),
		getSelectionText: () => new Promise((resolve) => {
			const listener = ({detail: event}) => {
				if (event.source === contentWindow && event.data.type === 'selectionResponse') {
					window.removeEventListener('chapter-editor-message', listener);
					resolve(event.data.payload.selectedText);
				}
			};
			window.addEventListener('chapter-editor-message', listener);
			post('getSelectionText'); // Request selection text from this chapter
		}),
		getFullHtml: () => new Promise((resolve) => {
			const listener = ({detail: event}) => {
				if (event.source === contentWindow && event.data.type === 'fullHtmlResponse') {
					window.removeEventListener('chapter-editor-message', listener);
					resolve(event.data.payload.html);
				}
			};
			window.addEventListener('chapter-editor-message', listener);
			post('prepareForGetFullHtml');
		}),
		setEditable: (isEditable) => post('setEditable', {isEditable}),
		cleanupSuggestion: () => post('cleanupAiSuggestion'),
		discardAiSuggestion: (from, to, originalFragmentJson) => post('discardAiSuggestion', {
			from,
			to,
			originalFragmentJson
		}),
		
		replaceRangeWithSuggestion: (from, to, newContentHtml) => new Promise((resolve) => {
			const listener = ({detail: event}) => {
				if (event.source === contentWindow && event.data.type === 'replacementComplete') {
					window.removeEventListener('chapter-editor-message', listener);
					resolve({
						finalRange: event.data.payload.finalRange,
						endCoords: event.data.payload.endCoords,
						suggestionRect: event.data.payload.suggestionRect
					});
				}
			};
			window.addEventListener('chapter-editor-message', listener);
			post('replaceRange', {from, to, newContentHtml});
		})
	};
};
