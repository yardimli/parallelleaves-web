let codexUpdateCb = null;
let codexFinishedCb = null;
let tmUpdateCb = null;
let coverUpdatedCb = null;

const UPLOAD_ENDPOINT = '/api/uploads';
const API_ENDPOINTS = {
	'splash:get-init-data': '/api/splash/init',
	'auth:login': '/api/auth/login',
	'auth:register': '/api/auth/register',
	'auth:logout': '/api/auth/logout',
	'auth:get-session': '/api/auth/session',
	'user:set-api-key': '/api/user/api-key',
	'i18n:get-lang-file': '/api/i18n/lang-file',
	'app:reset': '/api/app/reset',
	'session:getAvailableSpellCheckerLanguages': '/api/session/spellchecker/languages',
	'session:getCurrentSpellCheckerLanguage': '/api/session/spellchecker/current',
	'session:setSpellCheckerLanguage': '/api/session/spellchecker/set',
	'logs:get': '/api/logs',
	'languages:get-supported': '/api/languages/supported',
	'books:getAllWithCovers': '/api/books/with-covers',
	'books:getAllWithTranslationMemory': '/api/books/with-translation-memory',
	'books:getOne': '/api/books/get',
	'books:getFullManuscript': '/api/books/full-manuscript',
	'books:getAllBookContent': '/api/books/all-content',
	'books:getForExport': '/api/books/for-export',
	'books:createBlank': '/api/books/create-blank',
	'books:updateMeta': '/api/books/update-meta',
	'books:updateProseSettings': '/api/books/update-prose-settings',
	'books:updatePromptSettings': '/api/books/update-prompt-settings',
	'books:updateBookCover': '/api/books/update-cover',
	'books:delete': '/api/books/delete',
	'books:exportToDocx': '/api/books/export',
	'books:findHighestMarkerNumber': '/api/books/highest-marker',
	'chapters:updateField': '/api/chapters/update-field',
	'chapters:getRawContent': '/api/chapters/raw-content',
	'chapters:rename': '/api/chapters/rename',
	'chapters:delete': '/api/chapters/delete',
	'chapters:insert': '/api/chapters/insert',
	'chapters:getTranslationContext': '/api/chapters/translation-context',
	'document:read': '/api/documents/read',
	'document:import': '/api/documents/import',
	'llm:process-text': '/api/llm/process-text',
	'chat:send-message': '/api/chat/send-message',
	'ai:getModels': '/api/ai/models',
	'ai:generate-cover-prompt': '/api/ai/cover-prompt',
	'ai:generate-cover': '/api/ai/generate-cover',
	'log:translation': '/api/translation-log',
	'dictionary:get': '/api/dictionary/get',
	'dictionary:save': '/api/dictionary/save',
	'dictionary:getContentForAI': '/api/dictionary/for-ai',
	'tm:getAll': '/api/translation-memory/books',
	'tm:getDetails': '/api/translation-memory/details',
	'tm:delete': '/api/translation-memory/delete',
	'tm:deleteRow': '/api/translation-memory/delete-row',
	'tm:updateRow': '/api/translation-memory/update-row',
	'translation-memory:start': '/api/translation-memory/start',
	'translation-memory:process-batch': '/api/translation-memory/process-batch',
	'codex:getAll': '/api/codex/books',
	'codex:getDetails': '/api/codex/details',
	'codex:save': '/api/codex/save',
	'codex:reset': '/api/codex/reset',
	'codex:start': '/api/codex/start',
	'codex:process-batch': '/api/codex/process-batch',
	'codex:style-save': '/api/codex/style/save',
	'codex:style-start': '/api/codex/style/start',
	'codex:style-process-batch': '/api/codex/style/process-batch'
};

function showAjaxErrorDialog(message, title = 'Error') {
	let dialog = document.getElementById('ajax-error-dialog');
	if (!dialog) {
		dialog = document.createElement('dialog');
		dialog.id = 'ajax-error-dialog';
		dialog.className = 'modal';
		dialog.innerHTML = `
			<div class="modal-box">
				<h3 class="font-bold text-lg" data-ajax-error-title></h3>
				<p class="py-4 whitespace-pre-wrap" data-ajax-error-message></p>
				<div class="modal-action">
					<form method="dialog">
						<button class="btn btn-primary">OK</button>
					</form>
				</div>
			</div>
		`;
		document.body.appendChild(dialog);
	}
	
	dialog.querySelector('[data-ajax-error-title]').textContent = title;
	dialog.querySelector('[data-ajax-error-message]').textContent = message || 'An unexpected request error occurred.';
	
	if (typeof dialog.showModal === 'function') {
		if (!dialog.open) dialog.showModal();
	} else {
		alert(message);
	}
}

async function parseAjaxResponse(res, channel = 'request') {
	let json = null;
	try {
		json = await res.json();
	} catch (error) {
		const message = `The server returned ${res.status} ${res.statusText || ''}.`;
		showAjaxErrorDialog(message);
		throw new Error(message);
	}
	
	if (json.redirect) {
		window.location.href = json.redirect;
		return json;
	}
	
	if (!res.ok || json.success === false) {
		const message = json.message || `The ${channel} request failed with status ${res.status}.`;
		showAjaxErrorDialog(message);
		throw new Error(message);
	}
	
	return json;
}

async function rpcInvoke(channel, ...args) {
	const endpoint = API_ENDPOINTS[channel];
	if (!endpoint) throw new Error(`API endpoint not mapped: ${channel}`);
	const res = await fetch(endpoint, {
		method: 'POST',
		headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
		body: JSON.stringify({args})
	});
	const json = await parseAjaxResponse(res, channel);
	return json.data;
}

function rpcSend(channel, ...args) {
	const endpoint = API_ENDPOINTS[channel];
	if (!endpoint) {
		console.error(`API endpoint not mapped: ${channel}`);
		return;
	}
	fetch(endpoint, {
		method: 'POST',
		headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
		body: JSON.stringify({args})
	}).then(res => parseAjaxResponse(res, channel)).catch(console.error);
}

window.api = {
	openImportWindow: () => {
		window.location.href = '/import-document';
	},
	openChatWindow: (bookId) => {
		window.open(`/chat/${bookId}`, '_blank');
	},
	
	translationMemoryGenerateInBackground: async (bookId) => {
		try {
			const res = await rpcInvoke('translation-memory:start', bookId);
			if (!res || !res.job_id) {
				if (tmUpdateCb) tmUpdateCb({}, {finished: true, processedCount: 0});
				return;
			}
			const processNext = async () => {
				const status = await rpcInvoke('translation-memory:process-batch', res.job_id);
				if (tmUpdateCb) tmUpdateCb({}, {processed: status.processed_blocks, total: status.total_blocks});
				
				if (status.status === 'complete') {
					if (tmUpdateCb) tmUpdateCb({}, {finished: true, processedCount: status.processed_blocks});
				} else if (status.status === 'error') {
					if (tmUpdateCb) tmUpdateCb({}, {error: true, message: status.error_message});
				} else {
					setTimeout(processNext, 1000);
				}
			};
			processNext();
		} catch (err) {
			if (tmUpdateCb) tmUpdateCb({}, {error: true, message: err.message});
		}
	},
	onTranslationMemoryProgressUpdate: (cb) => {
		tmUpdateCb = cb;
	},
	
	getLangFile: (lang) => rpcInvoke('i18n:get-lang-file', lang),
	login: (credentials) => rpcInvoke('auth:login', credentials),
	register: (credentials) => rpcInvoke('auth:register', credentials),
	logout: () => rpcInvoke('auth:logout'),
	getSession: () => rpcInvoke('auth:get-session'),
	setApiKey: (key) => rpcInvoke('user:set-api-key', key),
	openExternalRegister: () => {
		window.location.href = '/register';
	},
	
	splashGetInitData: () => rpcInvoke('splash:get-init-data'),
	splashClose: () => {
		window.location.href = '/dashboard';
	},
	splashFinished: () => {
		window.location.href = '/dashboard';
	},
	openExternalUrl: (url) => window.open(url, '_blank'),
	appReset: () => rpcSend('app:reset'),
	
	getBooksWithCovers: () => rpcInvoke('books:getAllWithCovers'),
	getAllBooksWithTM: () => rpcInvoke('books:getAllWithTranslationMemory'),
	getOneBook: (bookId) => rpcInvoke('books:getOne', bookId),
	getFullManuscript: (bookId) => rpcInvoke('books:getFullManuscript', bookId),
	getAllBookContent: (bookId) => rpcInvoke('books:getAllBookContent', bookId),
	
	getBookForExport: (bookId) => rpcInvoke('books:getForExport', bookId),
	exportBookToDocx: async (data) => {
		const result = await rpcInvoke('books:exportToDocx', data);
		if (result && result.success && result.downloadUrl) {
			const a = document.createElement('a');
			a.href = result.downloadUrl;
			a.download = result.filename;
			a.click();
		}
		return result;
	},
	
	openEditor: (bookId) => {
		window.location.href = `/chapter-editor/${bookId}`;
	},
	
	codex: {
		startGeneration: async (bookId, options = {}) => {
			try {
				const res = await rpcInvoke('codex:start', bookId, options);
				if (res.status === 'complete') {
					if (codexFinishedCb) codexFinishedCb({}, {status: 'complete'});
					return;
				}
				const processNext = async () => {
					const status = await rpcInvoke('codex:process-batch', bookId, options);
					if (codexUpdateCb) codexUpdateCb({}, {
						statusKey: 'editor.codex.status.generating',
						progress: status.processed,
						total: status.total
					});
					
					if (status.status === 'complete') {
						if (codexFinishedCb) codexFinishedCb({}, {status: 'complete'});
					} else if (status.status === 'error') {
						if (codexFinishedCb) codexFinishedCb({}, {status: 'error', message: status.error_message});
					} else {
						setTimeout(processNext, 1000);
					}
				};
				processNext();
			} catch (err) {
				if (codexFinishedCb) codexFinishedCb({}, {status: 'error', message: err.message});
			}
		},
		onUpdate: (cb) => {
			codexUpdateCb = cb;
		},
		onFinished: (cb) => {
			codexFinishedCb = cb;
		}
	},
	
	updateProseSettings: (data) => rpcInvoke('books:updateProseSettings', data),
	updatePromptSettings: (data) => rpcInvoke('books:updatePromptSettings', data),
	updateBookMeta: (data) => rpcInvoke('books:updateMeta', data),
	createBlankBook: (data) => rpcInvoke('books:createBlank', data),
	
	updateBookCover: async (data) => {
		const res = await rpcInvoke('books:updateBookCover', data);
		if (res && res.success && coverUpdatedCb) {
			coverUpdatedCb({}, {bookId: data.bookId, imagePath: res.imagePath});
		}
		return res;
	},
	deleteBook: (bookId) => rpcInvoke('books:delete', bookId),
	onCoverUpdated: (cb) => {
		coverUpdatedCb = cb;
	},
	
	showOpenDocumentDialog: () => new Promise((resolve) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = '.txt,.docx';
		input.onchange = async (e) => {
			const file = e.target.files[0];
			if (!file) return resolve(null);
			const formData = new FormData();
			formData.append('file', file);
			const res = await fetch(UPLOAD_ENDPOINT, {method: 'POST', body: formData});
			const data = await parseAjaxResponse(res, 'upload');
			resolve(data.filePath);
		};
		input.click();
	}),
	readDocumentContent: (filePath) => rpcInvoke('document:read', filePath),
	importDocumentAsBook: async (data) => {
		const result = await rpcInvoke('document:import', data);
		if (result && result.success && result.bookId) {
			window.location.href = `/codex/${result.bookId}`;
		}
		return result;
	},
	onImportStatusUpdate: (cb) => {
	},
	
	getRawChapterContent: (data) => rpcInvoke('chapters:getRawContent', data),
	getTranslationContext: (data) => rpcInvoke('chapters:getTranslationContext', data),
	
	openChapterEditor: (data) => {
		window.location.href = `/chapter-editor/${data.bookId}/${data.chapterId}`;
	},
	onManuscriptScrollToChapter: (cb) => {
	},
	
	updateChapterField: (data) => rpcInvoke('chapters:updateField', data),
	renameChapter: (data) => rpcInvoke('chapters:rename', data),
	deleteChapter: (data) => rpcInvoke('chapters:delete', data),
	insertChapter: (data) => rpcInvoke('chapters:insert', data),
	
	processLLMText: (data) => rpcInvoke('llm:process-text', data),
	chatSendMessage: (data) => rpcInvoke('chat:send-message', data),
	getModels: () => rpcInvoke('ai:getModels'),
	generateCoverPrompt: (data) => rpcInvoke('ai:generate-cover-prompt', data),
	generateCover: (data) => rpcInvoke('ai:generate-cover', data),
	
	getAvailableSpellCheckerLanguages: () => Promise.resolve(['en-US']),
	getCurrentSpellCheckerLanguage: () => Promise.resolve('en-US'),
	setSpellCheckerLanguage: (lang) => Promise.resolve({success: true}),
	getSupportedLanguages: () => rpcInvoke('languages:get-supported'),
	
	getBookDictionary: (bookId) => rpcInvoke('dictionary:get', bookId),
	getDictionaryContentForAI: (bookId, type) => rpcInvoke('dictionary:getContentForAI', bookId, type),
	saveBookDictionary: (bookId, data) => rpcInvoke('dictionary:save', bookId, data),
	
	logTranslationEvent: (data) => rpcInvoke('log:translation', data),
	findHighestMarkerNumber: (sourceHtml, targetHtml) => rpcInvoke('books:findHighestMarkerNumber', sourceHtml, targetHtml),
	
	showOpenImageDialog: () => new Promise((resolve) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = 'image/*';
		input.onchange = async (e) => {
			const file = e.target.files[0];
			if (!file) return resolve(null);
			const formData = new FormData();
			formData.append('file', file);
			const res = await fetch(UPLOAD_ENDPOINT, {method: 'POST', body: formData});
			const data = await parseAjaxResponse(res, 'upload');
			resolve(data);
		};
		input.click();
	}),
	
	// UI Dashboards
	getLogs: (page) => rpcInvoke('logs:get', page),
	getTmBooks: () => rpcInvoke('tm:getAll'),
	getTmDetails: (bookId) => rpcInvoke('tm:getDetails', bookId),
	deleteTm: (bookId) => rpcInvoke('tm:delete', bookId),
	deleteTmRow: (id) => rpcInvoke('tm:deleteRow', id),
	updateTmRow: (id, source, target) => rpcInvoke('tm:updateRow', id, source, target),
	getCodexBooks: () => rpcInvoke('codex:getAll'),
	getCodexDetails: (bookId) => rpcInvoke('codex:getDetails', bookId),
	saveCodex: (bookId, content) => rpcInvoke('codex:save', bookId, content),
	resetCodex: (bookId) => rpcInvoke('codex:reset', bookId),
	startCodex: (bookId, options = {}) => rpcInvoke('codex:start', bookId, options),
	processCodexBatch: (bookId, options = {}) => rpcInvoke('codex:process-batch', bookId, options),
	saveStyleAnalysis: (bookId, content) => rpcInvoke('codex:style-save', bookId, content),
	startStyleAnalysis: (bookId, options = {}) => rpcInvoke('codex:style-start', bookId, options),
	processStyleAnalysisBatch: (bookId, options = {}) => rpcInvoke('codex:style-process-batch', bookId, options)
};
