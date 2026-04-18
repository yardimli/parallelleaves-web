const evtSource = new EventSource('/api/events');

async function rpcInvoke(channel, ...args) {
	const res = await fetch('/api/rpc', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ channel, args })
	});
	const json = await res.json();
	if (!json.success && json.message) throw new Error(json.message);
	return json.data;
}

function rpcSend(channel, ...args) {
	fetch('/api/rpc', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ channel, args })
	}).catch(console.error);
}

window.api = {
	// App Level
	openImportWindow: () => { window.location.href = '/import-document.html'; },
	openChatWindow: (novelId) => { window.open(`/chat-window.html?novelId=${novelId}`, '_blank'); },
	translationMemoryGenerateInBackground: (novelId) => rpcInvoke('translation-memory:generate-in-background', novelId),
	onTranslationMemoryProgressUpdate: (cb) => {
		evtSource.addEventListener('translation-memory:progress-update', (e) => cb({}, JSON.parse(e.data)));
	},
	
	getLangFile: (lang) => rpcInvoke('i18n:get-lang-file', lang),
	login: (credentials) => rpcInvoke('auth:login', credentials),
	logout: () => rpcInvoke('auth:logout'),
	getSession: () => rpcInvoke('auth:get-session'),
	openExternalRegister: () => rpcSend('auth:open-register-url'),
	
	splashGetInitData: () => rpcInvoke('splash:get-init-data'),
	splashCheckForUpdates: () => rpcInvoke('splash:check-for-updates'),
	splashClose: () => { window.location.href = '/index.html'; },
	splashFinished: () => { window.location.href = '/index.html'; },
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
			a.href = result.downloadUrl;
			a.download = result.filename;
			a.click();
		}
		return result;
	},
	
	openEditor: (novelId) => { window.location.href = `/chapter-editor.html?novelId=${novelId}`; },
	codex: {
		startGeneration: (novelId) => rpcSend('codex:start-generation', novelId),
		onUpdate: (cb) => evtSource.addEventListener('codex:update', (e) => cb({}, JSON.parse(e.data))),
		onFinished: (cb) => evtSource.addEventListener('codex:finished', (e) => cb({}, JSON.parse(e.data)))
	},
	updateProseSettings: (data) => rpcInvoke('novels:updateProseSettings', data),
	updatePromptSettings: (data) => rpcInvoke('novels:updatePromptSettings', data),
	updateNovelMeta: (data) => rpcInvoke('novels:updateMeta', data),
	createBlankNovel: (data) => rpcInvoke('novels:createBlank', data),
	updateNovelCover: (data) => rpcInvoke('novels:updateNovelCover', data),
	deleteNovel: (novelId) => rpcInvoke('novels:delete', novelId),
	
	onCoverUpdated: (cb) => evtSource.addEventListener('novels:cover-updated', (e) => cb({}, JSON.parse(e.data))),
	
	// File Dialog Replacements
	showOpenDocumentDialog: () => new Promise((resolve) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = '.txt,.docx';
		input.onchange = async (e) => {
			const file = e.target.files[0];
			if (!file) return resolve(null);
			const formData = new FormData();
			formData.append('file', file);
			const res = await fetch('/api/upload-temp', { method: 'POST', body: formData });
			const data = await res.json();
			resolve(data.filePath);
		};
		input.click();
	}),
	readDocumentContent: (filePath) => rpcInvoke('document:read', filePath),
	importDocumentAsNovel: async (data) => {
		const result = await rpcInvoke('document:import', data);
		// Redirect the browser to the editor once import is complete
		if (result && result.success && result.novelId) {
			window.location.href = `/chapter-editor.html?novelId=${result.novelId}`;
		}
		return result;
	},
	onImportStatusUpdate: (cb) => evtSource.addEventListener('import:status-update', (e) => cb({}, JSON.parse(e.data))),
	
	getTemplate: (templateName) => rpcInvoke('templates:get', templateName),
	getRawChapterContent: (data) => rpcInvoke('chapters:getRawContent', data),
	getTranslationContext: (data) => rpcInvoke('chapters:getTranslationContext', data),
	
	openChapterEditor: (data) => { window.location.href = `/chapter-editor.html?novelId=${data.novelId}&chapterId=${data.chapterId}`; },
	onManuscriptScrollToChapter: (cb) => evtSource.addEventListener('manuscript:scrollToChapter', (e) => cb({}, JSON.parse(e.data))),
	
	updateChapterField: (data) => rpcInvoke('chapters:updateField', data),
	renameChapter: (data) => rpcInvoke('chapters:rename', data),
	deleteChapter: (data) => rpcInvoke('chapters:delete', data),
	insertChapter: (data) => rpcInvoke('chapters:insert', data),
	
	processLLMText: (data) => rpcInvoke('llm:process-text', data),
	chatSendMessage: (data) => rpcInvoke('chat:send-message', data),
	getModels: () => rpcInvoke('ai:getModels'),
	generateCoverPrompt: (data) => rpcInvoke('ai:generate-cover-prompt', data),
	generateCover: (data) => rpcInvoke('ai:generate-cover', data),
	
	getAvailableSpellCheckerLanguages: () => rpcInvoke('session:getAvailableSpellCheckerLanguages'),
	getCurrentSpellCheckerLanguage: () => rpcInvoke('session:getCurrentSpellCheckerLanguage'),
	setSpellCheckerLanguage: (lang) => rpcInvoke('session:setSpellCheckerLanguage', lang),
	getSupportedLanguages: () => rpcInvoke('languages:get-supported'),
	
	getNovelForBackup: (novelId) => rpcInvoke('novels:getForBackup', novelId),
	restoreNovelFromBackup: (backupData) => rpcInvoke('novels:restoreFromBackup', backupData),
	saveBackupToFile: async (defaultFileName, jsonString) => {
		const result = await rpcInvoke('dialog:saveBackup', defaultFileName, jsonString);
		if (result && result.success && result.downloadUrl) {
			const a = document.createElement('a');
			a.href = result.downloadUrl;
			a.download = result.filename;
			a.click();
		}
		return result;
	},
	openBackupFile: () => new Promise((resolve) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = '.json';
		input.onchange = (e) => {
			const file = e.target.files[0];
			if (!file) return resolve(null);
			const reader = new FileReader();
			reader.onload = (ev) => resolve(ev.target.result);
			reader.readAsText(file);
		};
		input.click();
	}),
	
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
			const res = await fetch('/api/upload-temp', { method: 'POST', body: formData });
			const data = await res.json();
			resolve(data); // Returns { filePath, url }
		};
		input.click();
	})
};
