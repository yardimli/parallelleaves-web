let codexUpdateCb = null;
let codexFinishedCb = null;
let tmUpdateCb = null;
let coverUpdatedCb = null;

// Use relative path to point to the api folder one level up from /public/
const RPC_ENDPOINT = '../api/rpc.php';
const UPLOAD_ENDPOINT = '../api/upload.php';

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
	// App Level - Navigation is now relative to the current /public/ directory
	openImportWindow: () => { window.location.href = 'import-document.html'; },
	openChatWindow: (novelId) => { window.open(`chat-window.html?novelId=${novelId}`, '_blank'); },
	
	translationMemoryGenerateInBackground: async (novelId) => {
		try {
			const res = await rpcInvoke('translation-memory:start', novelId);
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
	logout: () => rpcInvoke('auth:logout'),
	getSession: () => rpcInvoke('auth:get-session'),
	openExternalRegister: () => { window.open('../register.php', '_blank'); },
	
	splashGetInitData: () => rpcInvoke('splash:get-init-data'),
	splashClose: () => { window.location.href = 'index.html'; },
	splashFinished: () => { window.location.href = 'index.html'; },
	openExternalUrl: (url) => window.open(url, '_blank'),
	appReset: () => rpcSend('app:reset'),
	
	getNovelsWithCovers: () => rpcInvoke('novels:getAllWithCovers'),
	getAllNovelsWithTM: () => rpcInvoke('novels:getAllWithTranslationMemory'),
	getOneNovel: (novelId) => rpcInvoke('novels:getOne', novelId),
	getFullManuscript: (novelId) => rpcInvoke('novels:getFullManuscript', novelId),
	getAllNovelContent: (novelId) => rpcInvoke('novels:getAllNovelContent', novelId),
	
	getNovelForExport: (novelId) => rpcInvoke('novels:getForExport', novelId),
	exportNovelToDocx: async (data) => {
		const result = await rpcInvoke('novels:exportToDocx', data);
		if (result && result.success && result.downloadUrl) {
			const a = document.createElement('a');
			// Convert absolute backend path to relative path
			a.href = '../' + result.downloadUrl.replace(/^\//, '');
			a.download = result.filename;
			a.click();
		}
		return result;
	},
	
	openEditor: (novelId) => { window.location.href = `chapter-editor.html?novelId=${novelId}`; },
	
	codex: {
		startGeneration: async (novelId) => {
			try {
				const res = await rpcInvoke('codex:start', novelId);
				if (res.status === 'complete') {
					if (codexFinishedCb) codexFinishedCb({}, { status: 'complete' });
					return;
				}
				const processNext = async () => {
					const status = await rpcInvoke('codex:process-batch', novelId);
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
	
	updateProseSettings: (data) => rpcInvoke('novels:updateProseSettings', data),
	updatePromptSettings: (data) => rpcInvoke('novels:updatePromptSettings', data),
	updateNovelMeta: (data) => rpcInvoke('novels:updateMeta', data),
	createBlankNovel: (data) => rpcInvoke('novels:createBlank', data),
	
	updateNovelCover: async (data) => {
		const res = await rpcInvoke('novels:updateNovelCover', data);
		if (res && res.success && coverUpdatedCb) {
			coverUpdatedCb({}, { novelId: data.novelId, imagePath: res.imagePath });
		}
		return res;
	},
	deleteNovel: (novelId) => rpcInvoke('novels:delete', novelId),
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
	importDocumentAsNovel: async (data) => {
		const result = await rpcInvoke('document:import', data);
		if (result && result.success && result.novelId) {
			window.location.href = `chapter-editor.html?novelId=${result.novelId}`;
		}
		return result;
	},
	onImportStatusUpdate: (cb) => { /* Stub for compatibility */ },
	
	getTemplate: (templateName) => rpcInvoke('templates:get', templateName),
	getRawChapterContent: (data) => rpcInvoke('chapters:getRawContent', data),
	getTranslationContext: (data) => rpcInvoke('chapters:getTranslationContext', data),
	
	openChapterEditor: (data) => { window.location.href = `chapter-editor.html?novelId=${data.novelId}&chapterId=${data.chapterId}`; },
	onManuscriptScrollToChapter: (cb) => { /* Stub for compatibility */ },
	
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
	
	getNovelDictionary: (novelId) => rpcInvoke('dictionary:get', novelId),
	getDictionaryContentForAI: (novelId, type) => rpcInvoke('dictionary:getContentForAI', novelId, type),
	saveNovelDictionary: (novelId, data) => rpcInvoke('dictionary:save', novelId, data),
	
	logTranslationEvent: (data) => rpcInvoke('log:translation', data),
	findHighestMarkerNumber: (sourceHtml, targetHtml) => rpcInvoke('novels:findHighestMarkerNumber', sourceHtml, targetHtml),
	
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
	})
};
