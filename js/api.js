let codexUpdateCb = null;
let codexFinishedCb = null;
let tmUpdateCb = null;
let coverUpdatedCb = null;

const RPC_ENDPOINT = 'api/rpc.php';
const UPLOAD_ENDPOINT = 'api/upload.php';

async function rpcInvoke(channel, ...args) {
	const res = await fetch(RPC_ENDPOINT, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ channel, args })
	});
	const json = await res.json();
	if (!json.success && json.message) throw new Error(json.message);
	return json.data;
}

function rpcSend(channel, ...args) {
	fetch(RPC_ENDPOINT, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ channel, args })
	}).catch(console.error);
}

window.api = {
	openImportWindow: () => { window.location.href = 'import-document.html'; },
	openChatWindow: (bookId) => { window.open(`chat-window.html?bookId=${bookId}`, '_blank'); },
	
	translationMemoryGenerateInBackground: async (bookId) => {
		try {
			const res = await rpcInvoke('translation-memory:start', bookId);
			if (!res || !res.job_id) {
				if (tmUpdateCb) tmUpdateCb({}, { finished: true, processedCount: 0 });
				return;
			}
			const processNext = async () => {
				const status = await rpcInvoke('translation-memory:process-batch', res.job_id);
				if (tmUpdateCb) tmUpdateCb({}, { processed: status.processed_blocks, total: status.total_blocks });
				
				if (status.status === 'complete') {
					if (tmUpdateCb) tmUpdateCb({}, { finished: true, processedCount: status.processed_blocks });
				} else if (status.status === 'error') {
					if (tmUpdateCb) tmUpdateCb({}, { error: true, message: status.error_message });
				} else {
					setTimeout(processNext, 1000);
				}
			};
			processNext();
		} catch (err) {
			if (tmUpdateCb) tmUpdateCb({}, { error: true, message: err.message });
		}
	},
	onTranslationMemoryProgressUpdate: (cb) => { tmUpdateCb = cb; },
	
	getLangFile: (lang) => rpcInvoke('i18n:get-lang-file', lang),
	login: (credentials) => rpcInvoke('auth:login', credentials),
	register: (credentials) => rpcInvoke('auth:register', credentials),
	logout: () => rpcInvoke('auth:logout'),
	getSession: () => rpcInvoke('auth:get-session'),
	setApiKey: (key) => rpcInvoke('user:set-api-key', key), // MODIFIED: Added setApiKey method
	openExternalRegister: () => { window.location.href = 'register.html'; },
	
	splashGetInitData: () => rpcInvoke('splash:get-init-data'),
	splashClose: () => { window.location.href = 'index.html'; },
	splashFinished: () => { window.location.href = 'index.html'; },
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
			a.href = '../' + result.downloadUrl.replace(/^\//, '');
			a.download = result.filename;
			a.click();
		}
		return result;
	},
	
	openEditor: (bookId) => { window.location.href = `chapter-editor.html?bookId=${bookId}`; },
	
	codex: {
		startGeneration: async (bookId) => {
			try {
				const res = await rpcInvoke('codex:start', bookId);
				if (res.status === 'complete') {
					if (codexFinishedCb) codexFinishedCb({}, { status: 'complete' });
					return;
				}
				const processNext = async () => {
					const status = await rpcInvoke('codex:process-batch', bookId);
					if (codexUpdateCb) codexUpdateCb({}, { statusKey: 'editor.codex.status.generating', progress: status.processed, total: status.total });
					
					if (status.status === 'complete') {
						if (codexFinishedCb) codexFinishedCb({}, { status: 'complete' });
					} else if (status.status === 'error') {
						if (codexFinishedCb) codexFinishedCb({}, { status: 'error', message: status.error_message });
					} else {
						setTimeout(processNext, 1000);
					}
				};
				processNext();
			} catch (err) {
				if (codexFinishedCb) codexFinishedCb({}, { status: 'error', message: err.message });
			}
		},
		onUpdate: (cb) => { codexUpdateCb = cb; },
		onFinished: (cb) => { codexFinishedCb = cb; }
	},
	
	updateProseSettings: (data) => rpcInvoke('books:updateProseSettings', data),
	updatePromptSettings: (data) => rpcInvoke('books:updatePromptSettings', data),
	updateBookMeta: (data) => rpcInvoke('books:updateMeta', data),
	createBlankBook: (data) => rpcInvoke('books:createBlank', data),
	
	updateBookCover: async (data) => {
		const res = await rpcInvoke('books:updateBookCover', data);
		if (res && res.success && coverUpdatedCb) {
			coverUpdatedCb({}, { bookId: data.bookId, imagePath: res.imagePath });
		}
		return res;
	},
	deleteBook: (bookId) => rpcInvoke('books:delete', bookId),
	onCoverUpdated: (cb) => { coverUpdatedCb = cb; },
	
	showOpenDocumentDialog: () => new Promise((resolve) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = '.txt,.docx';
		input.onchange = async (e) => {
			const file = e.target.files[0];
			if (!file) return resolve(null);
			const formData = new FormData();
			formData.append('file', file);
			const res = await fetch(UPLOAD_ENDPOINT, { method: 'POST', body: formData });
			const data = await res.json();
			resolve(data.filePath);
		};
		input.click();
	}),
	readDocumentContent: (filePath) => rpcInvoke('document:read', filePath),
	importDocumentAsBook: async (data) => {
		const result = await rpcInvoke('document:import', data);
		if (result && result.success && result.bookId) {
			window.location.href = `chapter-editor.html?bookId=${result.bookId}`;
		}
		return result;
	},
	onImportStatusUpdate: (cb) => { },
	
	getTemplate: (templateName) => rpcInvoke('templates:get', templateName),
	getRawChapterContent: (data) => rpcInvoke('chapters:getRawContent', data),
	getTranslationContext: (data) => rpcInvoke('chapters:getTranslationContext', data),
	
	openChapterEditor: (data) => { window.location.href = `chapter-editor.html?bookId=${data.bookId}&chapterId=${data.chapterId}`; },
	onManuscriptScrollToChapter: (cb) => { },
	
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
	setSpellCheckerLanguage: (lang) => Promise.resolve({ success: true }),
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
			const res = await fetch(UPLOAD_ENDPOINT, { method: 'POST', body: formData });
			const data = await res.json();
			resolve(data);
		};
		input.click();
	}),
	
	// UI Dashboards
	getLogs: (page) => rpcInvoke('logs:get', page),
	getTmBooks: () => rpcInvoke('tm:getAll'),
	getTmDetails: (bookId) => rpcInvoke('tm:getDetails', bookId),
	deleteTm: (bookId) => rpcInvoke('tm:delete', bookId),
	getCodexBooks: () => rpcInvoke('codex:getAll'),
	getCodexDetails: (bookId) => rpcInvoke('codex:getDetails', bookId),
	saveCodex: (bookId, content) => rpcInvoke('codex:save', bookId, content),
	resetCodex: (bookId) => rpcInvoke('codex:reset', bookId)
};
