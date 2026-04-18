import { initI18n, t, applyTranslationsTo, setLanguage, appLanguages } from './i18n.js';
import { exportBook } from './exporter.js';

/**
 * Compares two semantic version strings (e.g., '1.10.2' vs '1.2.0').
 * @param {string} v1 The first version string.
 * @param {string} v2 The second version string.
 * @returns {number} 1 if v1 > v2, -1 if v1 < v2, 0 if v1 === v2.
 */
function compareVersions(v1, v2) {
	const parts1 = v1.split('.').map(Number);
	const parts2 = v2.split('.').map(Number);
	const len = Math.max(parts1.length, parts2.length);
	for (let i = 0; i < len; i++) {
		const p1 = parts1[i] || 0;
		const p2 = parts2[i] || 0;
		if (p1 > p2) return 1;
		if (p1 < p2) return -1;
	}
	return 0;
}

document.addEventListener('DOMContentLoaded', async () => {
	await initI18n(true);
	
	const { version: appVersion } = await window.api.splashGetInitData();
	const dashboardTitle = t('dashboard.title');
	document.title = `${dashboardTitle} - v${appVersion}`;
	
	/**
	 * Displays a custom modal alert to prevent focus issues with native alerts.
	 * @param {string} message - The message to display.
	 * @param {string} [title='Error'] - The title for the alert modal.
	 */
	window.showAlert = function(message, title = t('common.error')) {
		const modal = document.getElementById('alert-modal');
		if (modal) {
			const modalTitle = modal.querySelector('#alert-modal-title');
			const modalContent = modal.querySelector('#alert-modal-content');
			if (modalTitle) modalTitle.textContent = title;
			if (modalContent) modalContent.textContent = message;
			modal.showModal();
		} else {
			// Fallback for pages without the modal
			alert(message);
		}
	};
	
	// --- DOM Elements ---
	const bookList = document.getElementById('book-list');
	const loadingMessage = document.getElementById('loading-message');
	const importDocBtnMenu = document.getElementById('import-doc-btn-menu');
	const newProjectBtnMenu = document.getElementById('new-project-btn-menu');
	const newProjectModal = document.getElementById('new-project-modal');
	const newProjectForm = document.getElementById('new-project-form');
	const newProjectSourceLangSelect = document.getElementById('new-project-source-language');
	const newProjectTargetLangSelect = document.getElementById('new-project-target-language');
	const authMenuSection = document.getElementById('auth-menu-section');
	const loginModal = document.getElementById('login-modal');
	const loginForm = document.getElementById('login-form');
	const loginErrorMsg = document.getElementById('login-error-message');
	const loginSubmitBtn = document.getElementById('login-submit-btn');
	const loginLangSelect = document.getElementById('login-language');
	
	const proseModal = document.getElementById('prose-settings-modal');
	const proseForm = document.getElementById('prose-settings-form');
	const proseBookIdInput = document.getElementById('prose-book-id');
	const saveProseBtn = document.getElementById('save-prose-settings-btn');
	const sourceLangSelect = document.getElementById('prose_source_language');
	const targetLangSelect = document.getElementById('prose_target_language');
	
	// Meta Modal Elements
	const metaModal = document.getElementById('meta-settings-modal');
	const metaForm = document.getElementById('meta-settings-form');
	const metaBookIdInput = document.getElementById('meta-book-id');
	const metaCoverPreview = document.getElementById('meta-cover-preview');
	const saveMetaBtn = document.getElementById('save-meta-settings-btn');
	const generateCoverBtn = document.getElementById('generate-cover-btn');
	const uploadCoverBtn = document.getElementById('upload-cover-btn');
	const deleteBookBtn = document.getElementById('delete-book-btn');
	
	// AI Cover Generation elements
	const metaCoverActions = document.getElementById('meta-cover-actions');
	const metaAiGenControls = document.getElementById('meta-ai-gen-controls');
	const metaAiPrompt = document.getElementById('meta-ai-prompt');
	const runGenerateCoverBtn = document.getElementById('run-generate-cover-btn');
	const cancelGenerateCoverBtn = document.getElementById('cancel-generate-cover-btn');
	const refreshBtn = document.getElementById('js-refresh-page-btn');
	
	let booksData = [];
	let stagedCover = null;
	let isRefreshingData = false;
	
	async function populateLanguages() {
		const supportedLanguages = await window.api.getSupportedLanguages();
		const langNames = Object.values(supportedLanguages).sort((a, b) => a.localeCompare(b));
		
		// Clear existing options before populating
		sourceLangSelect.innerHTML = '';
		targetLangSelect.innerHTML = '';
		newProjectSourceLangSelect.innerHTML = '';
		newProjectTargetLangSelect.innerHTML = '';
		
		langNames.forEach(lang => {
			sourceLangSelect.add(new Option(lang, lang));
			targetLangSelect.add(new Option(lang, lang));
			newProjectSourceLangSelect.add(new Option(lang, lang));
			newProjectTargetLangSelect.add(new Option(lang, lang));
		});
	}
	
	// --- Authentication Logic ---
	
	function populateLoginLanguageSelect() {
		const currentLang = localStorage.getItem('app_lang') || 'en';
		loginLangSelect.innerHTML = '';
		for (const [code, name] of Object.entries(appLanguages)) {
			const option = new Option(name, code);
			if (code === currentLang) {
				option.selected = true;
			}
			loginLangSelect.add(option);
		}
	}
	
	// MODIFIED: Dynamically fetch the modal template if it's missing, with a native prompt fallback
	async function promptForApiKey(currentKey) {
		let modal = document.getElementById('input-modal');
		
		// If the modal isn't in the DOM, fetch the template and inject it
		if (!modal) {
			try {
				const templateHtml = await window.api.getTemplate('modals/input-modal');
				if (templateHtml) {
					const wrapper = document.createElement('div');
					wrapper.innerHTML = templateHtml;
					document.body.appendChild(wrapper.firstElementChild);
					modal = document.getElementById('input-modal');
					applyTranslationsTo(modal);
				}
			} catch (e) {
				console.error('Failed to load input modal template', e);
			}
		}
		
		// Fallback to native browser prompt if template loading fails
		if (!modal) {
			const newKey = prompt(t('dashboard.setApiKeyLabel'), currentKey || '');
			if (newKey !== null) {
				try {
					await window.api.setApiKey(newKey.trim());
					const session = await window.api.getSession();
					updateAuthUI(session);
					window.showAlert(t('dashboard.apiKeySaved'), t('common.information'));
				} catch (error) {
					window.showAlert(error.message);
				}
			}
			return;
		}
		
		const title = document.getElementById('input-modal-title');
		const label = document.getElementById('input-modal-label');
		const input = document.getElementById('input-modal-input');
		const form = document.getElementById('input-modal-form');
		
		title.textContent = t('dashboard.setApiKeyTitle');
		label.textContent = t('dashboard.setApiKeyLabel');
		input.value = currentKey || '';
		input.type = 'password';
		
		// Clone form to remove previous event listeners
		const newForm = form.cloneNode(true);
		form.parentNode.replaceChild(newForm, form);
		
		newForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const newKey = newForm.elements[0].value.trim();
			try {
				await window.api.setApiKey(newKey);
				const session = await window.api.getSession();
				updateAuthUI(session);
				modal.close();
				window.showAlert(t('dashboard.apiKeySaved'), t('common.information'));
			} catch (error) {
				window.showAlert(error.message);
			}
		});
		
		modal.showModal();
	}
	
	function updateAuthUI(session) {
		const authDivider = document.getElementById('auth-divider');
		
		if (session && session.user) {
			authMenuSection.innerHTML = `
                <li class="menu-title"><span>${t('dashboard.welcome', { username: session.user.username })}</span></li>
                <li><a id="api-key-btn"><i class="bi bi-key"></i>${t('dashboard.setApiKey')}</a></li>
                <li><a id="logout-btn"><i class="bi bi-box-arrow-right"></i>${t('dashboard.signOut')}</a></li>
            `;
			document.getElementById('logout-btn').addEventListener('click', handleLogout);
			document.getElementById('api-key-btn').addEventListener('click', () => promptForApiKey(session.user.openrouter_api_key));
			
			if (authDivider) authDivider.classList.remove('hidden');
			
			loadInitialData(); // Load projects only when logged in
			window.api.getModels().catch(err => {
				console.error('Failed to pre-fetch AI models on startup:', err);
			});
		} else {
			authMenuSection.innerHTML = `
                 <li><a id="login-btn"><i class="bi bi-box-arrow-in-right"></i>${t('dashboard.signIn')}</a></li>
            `;
			document.getElementById('login-btn').addEventListener('click', () => loginModal.showModal());
			if (authDivider) authDivider.classList.add('hidden');
			
			bookList.innerHTML = `<p class="text-base-content/70 text-center">${t('dashboard.signInPrompt')}</p>`;
			loadingMessage.style.display = 'none';
			
			loginModal.showModal();
		}
	}
	
	async function handleLogin(event) {
		event.preventDefault();
		loginErrorMsg.classList.add('hidden');
		setButtonLoading(loginSubmitBtn, true);
		
		const username = loginForm.elements.username.value;
		const password = loginForm.elements.password.value;
		const lang = loginForm.elements.language.value;
		
		try {
			const result = await window.api.login({ username, password });
			if (result.success) {
				if (lang !== (localStorage.getItem('app_lang') || 'en')) {
					await setLanguage(lang);
				} else {
					loginModal.close();
					updateAuthUI(result.session);
				}
			} else {
				loginErrorMsg.textContent = t(result.message) || t('dashboard.login.failed');
				loginErrorMsg.classList.remove('hidden');
			}
		} catch (error) {
			loginErrorMsg.textContent = error.message;
			loginErrorMsg.classList.remove('hidden');
		} finally {
			setButtonLoading(loginSubmitBtn, false);
		}
	}
	
	async function handleLogout() {
		try {
			await window.api.logout();
			// Reload the page to reset the UI to the logged-out state
			window.location.reload();
		} catch (error) {
			console.error('Logout failed:', error);
			window.showAlert(t('common.error') + ': ' + error.message);
		}
	}
	
	async function initAuth() {
		const session = await window.api.getSession();
		updateAuthUI(session);
		populateLoginLanguageSelect();
		loginForm.addEventListener('submit', handleLogin);
		
		loginLangSelect.addEventListener('change', async (e) => {
			const newLang = e.target.value;
			await setLanguage(newLang);
		});
		
		document.getElementById('signup-link').addEventListener('click', (e) => {
			e.preventDefault();
			window.api.openExternalRegister();
		});
	}
	
	function setButtonLoading(button, isLoading) {
		const content = button.querySelector('.js-btn-content');
		const spinner = button.querySelector('.js-btn-spinner');
		button.disabled = isLoading;
		if (content) content.classList.toggle('hidden', isLoading);
		if (spinner) spinner.classList.toggle('hidden', !isLoading);
	}
	
	function openProseSettingsModal(book) {
		proseBookIdInput.value = book.id;
		sourceLangSelect.value = book.source_language || 'English';
		targetLangSelect.value = book.target_language || 'English';
		proseModal.showModal();
	}
	
	function openMetaSettingsModal(book) {
		stagedCover = null;
		metaBookIdInput.value = book.id;
		metaForm.querySelector('#meta-title').value = book.title;
		metaForm.querySelector('#meta-author').value = book.author || '';
		
		const currentBook = booksData.find(n => n.id === book.id);
		if (currentBook && currentBook.cover_path) {
			metaCoverPreview.innerHTML = `<img src="${currentBook.cover_path}?t=${Date.now()}" alt="${t('dashboard.metaSettings.altCurrentCover')}" class="w-full h-auto">`;
		} else {
			metaCoverPreview.innerHTML = `<img src="./assets/bookcover-placeholder.jpg" alt="${t('dashboard.metaSettings.altNoCover')}" class="w-full h-auto">`;
		}
		
		metaAiGenControls.classList.add('hidden');
		metaCoverActions.classList.remove('hidden');
		
		metaModal.showModal();
	}
	
	function updateBookCardUI(bookId) {
		const book = booksData.find(n => n.id === bookId);
		if (!book) return;
		
		const card = bookList.querySelector(`[data-book-id='${bookId}']`);
		if (card) {
			card.querySelector('.card-title').textContent = book.title;
			card.querySelector('.text-base-content\\/80').textContent = book.author || t('common.unknownAuthor');
		}
	}
	
	async function loadInitialData() {
		if (isRefreshingData) {
			return;
		}
		isRefreshingData = true;
		
		try {
			booksData = await window.api.getBooksWithCovers();
			renderBooks();
		} catch (error) {
			console.error('Failed to load initial data:', error);
			loadingMessage.textContent = t('dashboard.errorLoading');
		} finally {
			isRefreshingData = false;
		}
	}
	
	function applyListViewStyles() {
		const cards = document.querySelectorAll('#book-list > .card');
		
		cards.forEach(card => {
			// Remove any grid-specific layout classes that might exist
			card.classList.remove('card-compact', 'flex-col');
			// Add list-view layout classes (DaisyUI side card for horizontal layout)
			card.classList.add('card-side', 'flex-row');
			// Constrain the width of the cover image in list view for a better look
			card.querySelector('figure')?.classList.add('max-w-[200px]');
		});
	}
	
	function renderBooks() {
		loadingMessage.style.display = 'none';
		
		if (booksData.length === 0) {
			bookList.innerHTML = `<p class="text-base-content/70 text-center" data-i18n="dashboard.noProjects">${t('dashboard.noProjects')}</p>`;
			return;
		}
		
		bookList.innerHTML = '';
		booksData.forEach(book => {
			const bookCard = document.createElement('div');
			bookCard.className = 'card bg-base-200 shadow-xl transition-shadow h-full flex';
			bookCard.dataset.bookId = book.id;
			
			const coverHtml = book.cover_path
				? `<img src="${book.cover_path}?t=${new Date(book.updated_at).getTime()}" alt="${t('dashboard.metaSettings.altCoverFor', { title: book.title })}" class="w-full">`
				: `<img src="./assets/bookcover-placeholder.jpg" alt="${t('dashboard.metaSettings.altNoCover')}" class="w-full h-auto">`;
			
			// MODIFIED: Added Codex and Translation Memory buttons directly to the book card actions
			bookCard.innerHTML = `
                <figure class="cursor-pointer js-open-editor">${coverHtml}</figure>
                <div class="card-body flex flex-col flex-grow">
                    <h2 class="card-title js-open-editor cursor-pointer">${book.title}</h2>
                    <p class="text-base-content/80 -mt-2 mb-2">${book.author || t('common.unknownAuthor')}</p>
                    
                    <!-- Stats Section -->
                    <div class="text-xs space-y-2 text-base-content/70 mt-auto">
                        <!-- Progress Bar -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="font-semibold" data-i18n="dashboard.card.progress">Progress</span>
                                <span class="js-progress-percent">0%</span>
                            </div>
                            <progress class="progress progress-primary w-full js-progress-bar" value="0" max="100"></progress>
                        </div>
                        
                        <!-- Word Counts -->
                        <div class="grid grid-cols-5 gap-x-4">
                            <div>
                                <div class="font-semibold" data-i18n="dashboard.card.sourceWords">Source</div>
                                <div class="js-source-words">0 words</div>
                            </div>
                            <div>
                                <div class="font-semibold" data-i18n="dashboard.card.targetWords">Target</div>
                                <div class="js-target-words">0 words</div>
                            </div>
                            <div>
                                <div class="font-semibold" data-i18n="dashboard.card.chapters">Chapters</div>
                                <div class="js-chapter-count">0</div>
                            </div>
                              <div>
                                <div class="font-semibold" data-i18n="dashboard.card.created">Created:</div>
                                <div class="js-created-date"></div>
                             </div>
                             <div>
                                <div class="font-semibold" data-i18n="dashboard.card.updated">Updated:</div>
                                <div class="js-updated-date"></div>
                             </div>
                        </div>
                    </div>
                    
                    <div class="card-actions start items-center mt-4">
                        <button class="btn btn-ghost btn-sm js-meta-settings" data-i18n-title="common.edit">
                            <i class="bi bi-pencil-square text-lg"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm js-prose-settings" data-i18n-title="dashboard.proseSettings.title">
                            <i class="bi bi-translate text-lg"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm js-open-codex" data-i18n-title="dashboard.codexEditor" title="Codex Editor">
                            <i class="bi bi-journal-bookmark-fill text-lg"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm js-open-tm" data-i18n-title="dashboard.translationMemory" title="Translation Memory">
                            <i class="bi bi-book-fill text-lg"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm js-export-docx" data-i18n-title="export.exportDocx">
                            <i class="bi bi-file-earmark-word text-lg"></i>
                        </button>
                    </div>
                </div>
            `;
			
			const progressBar = bookCard.querySelector('.js-progress-bar');
			const progressPercent = bookCard.querySelector('.js-progress-percent');
			const sourceWords = bookCard.querySelector('.js-source-words');
			const targetWords = bookCard.querySelector('.js-target-words');
			const chapterCountEl = bookCard.querySelector('.js-chapter-count');
			const createdDateEl = bookCard.querySelector('.js-created-date');
			const updatedDateEl = bookCard.querySelector('.js-updated-date');
			
			let progress = 0;
			if (book.source_word_count > 0) {
				progress = Math.round((book.target_word_count / book.source_word_count) * 100);
			} else if (book.target_word_count > 0) {
				progress = 100;
			}
			progress = Math.min(100, Math.max(0, progress));
			
			if (progressBar) progressBar.value = progress;
			if (progressPercent) progressPercent.textContent = `${progress}%`;
			
			const numberFormat = new Intl.NumberFormat();
			const wordLabel = t('common.words');
			
			if (sourceWords) sourceWords.textContent = `${numberFormat.format(book.source_word_count)} ${wordLabel}`;
			if (targetWords) targetWords.textContent = `${numberFormat.format(book.target_word_count)} ${wordLabel}`;
			if (chapterCountEl) chapterCountEl.textContent = book.chapter_count;
			
			const dateFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' };
			if (createdDateEl && book.created_at) {
				createdDateEl.textContent = new Date(book.created_at).toLocaleDateString(undefined, dateFormatOptions);
			}
			if (updatedDateEl && book.updated_at) {
				updatedDateEl.textContent = new Date(book.updated_at).toLocaleDateString(undefined, dateFormatOptions);
			}
			
			bookCard.querySelectorAll('.js-open-editor').forEach(el => el.addEventListener('click', () => window.api.openEditor(book.id)));
			bookCard.querySelector('.js-prose-settings').addEventListener('click', () => openProseSettingsModal(book));
			bookCard.querySelector('.js-meta-settings').addEventListener('click', () => openMetaSettingsModal(book));
			bookCard.querySelector('.js-export-docx').addEventListener('click', () => exportBook(book.id));
			
			// MODIFIED: Added event listeners to navigate directly to the specific book's Codex and TM pages
			bookCard.querySelector('.js-open-codex').addEventListener('click', () => {
				window.location.href = `codex-editor.html?bookId=${book.id}`;
			});
			bookCard.querySelector('.js-open-tm').addEventListener('click', () => {
				window.location.href = `translation-memory.html?bookId=${book.id}`;
			});
			
			bookList.appendChild(bookCard);
		});
		
		applyListViewStyles();
		applyTranslationsTo(bookList);
	}
	
	// --- Event Listeners ---
	
	if (refreshBtn) {
		refreshBtn.addEventListener('click', () => {
			window.location.reload();
		});
	}
	
	if (importDocBtnMenu) {
		importDocBtnMenu.addEventListener('click', () => {
			window.api.openImportWindow();
		});
	}
	
	if (newProjectBtnMenu) {
		newProjectBtnMenu.addEventListener('click', () => {
			newProjectForm.reset();
			newProjectModal.showModal();
		});
	}
	
	if (newProjectForm) {
		newProjectForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const formData = new FormData(newProjectForm);
			const data = {
				title: formData.get('title'),
				source_language: formData.get('source_language'),
				target_language: formData.get('target_language'),
			};
			
			if (!data.title.trim()) {
				showAlert(t('dashboard.newProjectModal.errorNoTitle'));
				return;
			}
			
			try {
				const result = await window.api.createBlankBook(data);
				if (result.success) {
					newProjectModal.close();
					await loadInitialData(); // Refresh the project list
				} else {
					throw new Error(result.message || 'Failed to create project.');
				}
			} catch (error) {
				console.error('Failed to create blank project:', error);
				showAlert(t('dashboard.newProjectModal.errorCreate', { message: error.message }));
			}
		});
	}
	
	saveProseBtn.addEventListener('click', async (e) => {
		e.preventDefault();
		const bookId = parseInt(proseBookIdInput.value, 10);
		const formData = new FormData(proseForm);
		const data = {
			bookId,
			source_language: formData.get('prose_source_language'),
			target_language: formData.get('prose_target_language'),
		};
		
		try {
			await window.api.updateProseSettings(data);
			const bookIndex = booksData.findIndex(n => n.id === bookId);
			if (bookIndex !== -1) Object.assign(booksData[bookIndex], data);
			proseModal.close();
		} catch (error) {
			console.error('Failed to save language settings:', error);
		}
	});
	
	saveMetaBtn.addEventListener('click', async (e) => {
		e.preventDefault();
		const bookId = parseInt(metaBookIdInput.value, 10);
		
		const formData = new FormData(metaForm);
		const data = {
			bookId,
			title: formData.get('title'),
			author: formData.get('author'),
		};
		
		try {
			await window.api.updateBookMeta(data);
			const bookIndex = booksData.findIndex(n => n.id === bookId);
			if (bookIndex !== -1) Object.assign(booksData[bookIndex], data);
			updateBookCardUI(bookId);
			
			if (stagedCover) {
				await window.api.updateBookCover({ bookId, coverInfo: stagedCover });
			}
			
			metaModal.close();
		} catch (error) {
			console.error('Failed to save meta settings:', error);
			window.showAlert('Error saving settings: ' + error.message);
		}
	});
	
	generateCoverBtn.addEventListener('click', async () => {
		metaCoverActions.classList.add('hidden');
		metaAiGenControls.classList.remove('hidden');
		metaAiPrompt.value = '';
		metaAiPrompt.disabled = true;
		
		const bookTitle = metaForm.querySelector('#meta-title').value;
		try {
			const result = await window.api.generateCoverPrompt({ bookTitle });
			if (result.success && result.prompt) {
				metaAiPrompt.value = result.prompt;
			} else {
				metaAiPrompt.value = `A book cover for a story titled "${bookTitle}"`;
			}
		} catch (error) {
			console.error('Failed to generate cover prompt:', error);
			metaAiPrompt.value = `A book cover for a story titled "${bookTitle}"`;
		} finally {
			metaAiPrompt.disabled = false;
		}
	});
	
	cancelGenerateCoverBtn.addEventListener('click', () => {
		metaAiGenControls.classList.add('hidden');
		metaCoverActions.classList.remove('hidden');
	});
	
	runGenerateCoverBtn.addEventListener('click', async () => {
		const bookId = parseInt(metaBookIdInput.value, 10);
		const prompt = metaAiPrompt.value.trim();
		if (!prompt) {
			showAlert('Please enter an image prompt.');
			return;
		}
		
		setButtonLoading(runGenerateCoverBtn, true);
		metaCoverPreview.innerHTML = `<div class="flex flex-col items-center justify-center h-full gap-2">
			<span class="loading loading-spinner loading-lg"></span>
			<p class="text-sm text-base-content/60">Generating image...</p>
		</div>`;
		
		try {
			const result = await window.api.generateCover({ bookId, prompt });
			if (result.success && result.filePath) {
				// MODIFIED: Use 'existing' type and localPath for already saved generated images
				stagedCover = { type: 'existing', data: result.localPath };
				metaCoverPreview.innerHTML = `<img src="${result.filePath}?t=${Date.now()}" alt="${t('dashboard.metaSettings.altStagedCover')}" class="w-full h-auto">`;
			} else {
				throw new Error(result.message || 'Failed to generate cover.');
			}
		} catch (error) {
			console.error('Failed to generate cover:', error);
			window.showAlert('Error generating cover: ' + error.message);
			const currentBook = booksData.find(n => n.id === bookId);
			if (currentBook && currentBook.cover_path) {
				metaCoverPreview.innerHTML = `<img src="${currentBook.cover_path}?t=${Date.now()}" alt="${t('dashboard.metaSettings.altCurrentCover')}" class="w-full h-auto">`;
			} else {
				metaCoverPreview.innerHTML = `<img src="./assets/bookcover-placeholder.jpg" alt="${t('dashboard.metaSettings.altNoCover')}" class="w-full h-auto">`;
			}
		} finally {
			setButtonLoading(runGenerateCoverBtn, false);
		}
	});
	
	uploadCoverBtn.addEventListener('click', async () => {
		const result = await window.api.showOpenImageDialog();
		// MODIFIED: Handle the result object properly
		if (result && result.success) {
			stagedCover = { type: 'local', data: result.filePath };
			metaCoverPreview.innerHTML = `<img src="${result.url}" alt="${t('dashboard.metaSettings.altStagedCover')}" class="w-full h-auto">`;
		}
	});
	
	deleteBookBtn.addEventListener('click', async () => {
		const bookId = parseInt(metaBookIdInput.value, 10);
		const book = booksData.find(n => n.id === bookId);
		if (!book) return;
		
		const confirmation = confirm(t('dashboard.metaSettings.deleteConfirm', { title: book.title }));
		if (confirmation) {
			try {
				await window.api.deleteBook(bookId);
				booksData = booksData.filter(n => n.id !== bookId);
				metaModal.close();
				renderBooks();
			} catch (error) {
				console.error('Failed to delete project:', error);
				window.showAlert(t('dashboard.metaSettings.errorDelete'));
			}
		}
	});
	
	// --- IPC Listeners ---
	
	window.api.onCoverUpdated((event, { bookId, imagePath }) => {
		const bookIndex = booksData.findIndex(n => n.id === bookId);
		if (bookIndex !== -1) {
			booksData[bookIndex].cover_path = imagePath;
		}
		
		const card = bookList.querySelector(`[data-book-id='${bookId}']`);
		if (card) {
			const figure = card.querySelector('figure');
			if (figure) {
				const book = booksData.find(n => n.id === bookId);
				const altText = t('dashboard.metaSettings.altCoverFor', { title: book ? book.title : bookId });
				figure.innerHTML = `<img src="${imagePath}?t=${Date.now()}" alt="${altText}" class="w-full">`;
			}
		}
	});
	
	// --- Initializations ---
	populateLanguages();
	initAuth();
	
	window.addEventListener('focus', () => {
		// Only refresh if the user is logged in (auth container has a logout button).
		if (document.getElementById('logout-btn')) {
			//loadInitialData();
		}
	});
});
