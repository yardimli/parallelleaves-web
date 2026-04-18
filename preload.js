const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('api', {
	// --- App Level ---
	openImportWindow: () => ipcRenderer.send('app:open-import-window'),
	openChatWindow: (bookId) => ipcRenderer.send('app:openChatWindow', bookId),
	translationMemoryGenerateInBackground: (bookId) => ipcRenderer.invoke('translation-memory:generate-in-background', bookId),
	onTranslationMemoryProgressUpdate: (callback) => ipcRenderer.on('translation-memory:progress-update', (event, ...args) => callback(...args)),
	
	getLangFile: (lang) => ipcRenderer.invoke('i18n:get-lang-file', lang),
	login: (credentials) => ipcRenderer.invoke('auth:login', credentials),
	logout: () => ipcRenderer.invoke('auth:logout'),
	getSession: () => ipcRenderer.invoke('auth:get-session'),
	openExternalRegister: () => ipcRenderer.send('auth:open-register-url'),
	
	splashGetInitData: () => ipcRenderer.invoke('splash:get-init-data'),
	splashClose: () => ipcRenderer.send('splash:close'),
	splashFinished: () => ipcRenderer.send('splash:finished'),
	openExternalUrl: (url) => ipcRenderer.send('app:open-external-url', url),
	
	// --- Dashboard/Book Creation ---
	getBooksWithCovers: () => ipcRenderer.invoke('books:getAllWithCovers'),
	
	// Handlers to get books that have a TM
	getAllBooksWithTM: () => ipcRenderer.invoke('books:getAllWithTranslationMemory'),
	getOneBook: (bookId) => ipcRenderer.invoke('books:getOne', bookId),
	getFullManuscript: (bookId) => ipcRenderer.invoke('books:getFullManuscript', bookId),
	getAllBookContent: (bookId) => ipcRenderer.invoke('books:getAllBookContent', bookId),
	
	getBookForExport: (bookId) => ipcRenderer.invoke('books:getForExport', bookId),
	exportBookToDocx: (data) => ipcRenderer.invoke('books:exportToDocx', data),
	
	openEditor: (bookId) => ipcRenderer.send('books:openEditor', bookId),
	codex: {
		startGeneration: (bookId) => ipcRenderer.send('codex:start-generation', bookId),
		onUpdate: (callback) => ipcRenderer.on('codex:update', callback),
		onFinished: (callback) => ipcRenderer.on('codex:finished', callback)
	},
	updateProseSettings: (data) => ipcRenderer.invoke('books:updateProseSettings', data),
	updatePromptSettings: (data) => ipcRenderer.invoke('books:updatePromptSettings', data),
	
	updateBookMeta: (data) => ipcRenderer.invoke('books:updateMeta', data),
	createBlankBook: (data) => ipcRenderer.invoke('books:createBlank', data),
	updateBookCover: (data) => ipcRenderer.invoke('books:updateBookCover', data),
	deleteBook: (bookId) => ipcRenderer.invoke('books:delete', bookId),
	
	onCoverUpdated: (callback) => ipcRenderer.on('books:cover-updated', callback),
	
	// --- Document Import ---
	showOpenDocumentDialog: () => ipcRenderer.invoke('dialog:showOpenDocument'),
	readDocumentContent: (filePath) => ipcRenderer.invoke('document:read', filePath),
	importDocumentAsBook: (data) => ipcRenderer.invoke('document:import', data),
	onImportStatusUpdate: (callback) => ipcRenderer.on('import:status-update', (event, ...args) => callback(...args)),
	
	// --- Editor Specific APIs ---
	getTemplate: (templateName) => ipcRenderer.invoke('templates:get', templateName),
	getRawChapterContent: (data) => ipcRenderer.invoke('chapters:getRawContent', data),
	getTranslationContext: (data) => ipcRenderer.invoke('chapters:getTranslationContext', data),
	
	openChapterEditor: (data) => ipcRenderer.send('chapters:openEditor', data),
	onManuscriptScrollToChapter: (callback) => ipcRenderer.on('manuscript:scrollToChapter', callback),
	
	updateChapterField: (data) => ipcRenderer.invoke('chapters:updateField', data),
	renameChapter: (data) => ipcRenderer.invoke('chapters:rename', data),
	deleteChapter: (data) => ipcRenderer.invoke('chapters:delete', data),
	insertChapter: (data) => ipcRenderer.invoke('chapters:insert', data),
	
	// LLM
	processLLMText: (data) => ipcRenderer.invoke('llm:process-text', data),
	chatSendMessage: (data) => ipcRenderer.invoke('chat:send-message', data),
	getModels: () => ipcRenderer.invoke('ai:getModels'),
	generateCoverPrompt: (data) => ipcRenderer.invoke('ai:generate-cover-prompt', data),
	generateCover: (data) => ipcRenderer.invoke('ai:generate-cover', data),
	
	// Spellchecker APIs
	getAvailableSpellCheckerLanguages: () => ipcRenderer.invoke('session:getAvailableSpellCheckerLanguages'),
	getCurrentSpellCheckerLanguage: () => ipcRenderer.invoke('session:getCurrentSpellCheckerLanguage'),
	setSpellCheckerLanguage: (lang) => ipcRenderer.invoke('session:setSpellCheckerLanguage', lang),
	
	getSupportedLanguages: () => ipcRenderer.invoke('languages:get-supported'),
	
	getBookDictionary: (bookId) => ipcRenderer.invoke('dictionary:get', bookId),
	getDictionaryContentForAI: (bookId, type) => ipcRenderer.invoke('dictionary:getContentForAI', bookId, type),
	saveBookDictionary: (bookId, data) => ipcRenderer.invoke('dictionary:save', bookId, data),
	
	// API for logging translation events
	logTranslationEvent: (data) => ipcRenderer.invoke('log:translation', data),
	
	findHighestMarkerNumber: (sourceHtml, targetHtml) => ipcRenderer.invoke('books:findHighestMarkerNumber', sourceHtml, targetHtml)
});
