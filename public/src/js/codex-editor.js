// MODIFIED: Added applyTranslationsTo to i18n module imports
import { initI18n, t, applyTranslationsTo } from './i18n.js';

document.addEventListener('DOMContentLoaded', async () => {
	await initI18n();
	// MODIFIED: Invoked applyTranslationsTo on document body to process static DOM nodes
	applyTranslationsTo(document.body);
	
	const urlBookId = window.routeParams?.bookId || null;
	const modelData = window.initialModels?.models || [];
	const defaultModel = localStorage.getItem('parallel-leaves-codex-model') || 'openai/gpt-5.4';
	const defaultTemperature = localStorage.getItem('parallel-leaves-codex-temperature') || '0.5';
	let activeBook = null;
	let isGenerating = false;
	
	// DOM elements
	const loadingTextEl = document.getElementById('codex-loading-text');
	const editorViewEl = document.getElementById('codex-editor-view');
	const statusEl = document.getElementById('codex-generation-status');
	const titleEl = document.getElementById('codex-title');
	const modelSelectEl = document.getElementById('codex-model-select');
	const languageSelectEl = document.getElementById('codex-language-select');
	const temperatureInputEl = document.getElementById('codex-temperature-input');
	const rebuildBtnEl = document.getElementById('codex-rebuild-btn');
	const progressContainerEl = document.getElementById('codex-progress-container');
	const progressBarEl = document.getElementById('codex-progress-bar');
	const progressPercentEl = document.getElementById('codex-progress-percent');
	const textareaEl = document.getElementById('codex-textarea');
	const saveBtnEl = document.getElementById('codex-save-btn');
	
	const escapeHtml = (value) => String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
	
	// MODIFIED: Configured status parsing to accurately return translation lookups
	function statusLabel (book) {
		const status = book?.codex_status || 'none';
		if (status === 'complete') return t('editor.codex.statusLabel.complete');
		if (status === 'generating' && Number(book?.codex_chunks_total || 0) > 0) {
			return t('editor.codex.statusLabel.incomplete', {
				processed: Number(book.codex_chunks_processed || 0),
				total: Number(book.codex_chunks_total || 0)
			});
		}
		if (status === 'error') return t('editor.codex.statusLabel.error');
		return t('editor.codex.statusLabel.incomplete_none');
	}
	
	function modelOptionsHtml (selectedModel = defaultModel) {
		return modelData.map(group => `
      <optgroup label="${escapeHtml(group.group)}">
        ${group.models.map(model => `
          <option value="${escapeHtml(model.id)}" ${model.id === selectedModel ? 'selected' : ''}>${escapeHtml(model.name)}</option>
        `).join('')}
      </optgroup>
    `).join('');
	}
	
	async function editCodex (bookId) {
		if (loadingTextEl) {
			loadingTextEl.classList.remove('hidden');
			loadingTextEl.textContent = t('editor.codex.editor.loadingDetails');
		}
		if (editorViewEl) {
			editorViewEl.classList.add('hidden');
		}
		
		try {
			const book = await window.api.getCodexDetails(bookId);
			if (!book) throw new Error('Book not found.');
			activeBook = book;
			
			if (loadingTextEl) loadingTextEl.classList.add('hidden');
			if (editorViewEl) {
				editorViewEl.classList.remove('hidden');
				// MODIFIED: Processes newly visible container to map contextual translations
				applyTranslationsTo(editorViewEl);
			}
			
			// Set dynamic context headers
			if (statusEl) statusEl.textContent = statusLabel(book);
			if (titleEl) titleEl.textContent = t('editor.codex.editor.editingFor', { title: book.title });
			
			if (modelSelectEl) {
				modelSelectEl.innerHTML = modelOptionsHtml(defaultModel);
			}
			
			if (languageSelectEl) {
				languageSelectEl.innerHTML = `
          <option value="${escapeHtml(book.source_language)}">${escapeHtml(t('editor.codex.editor.source'))} (${escapeHtml(book.source_language)})</option>
          <option value="${escapeHtml(book.target_language)}" selected>${escapeHtml(t('editor.codex.editor.target'))} (${escapeHtml(book.target_language)})</option>
          <option value="both">${escapeHtml(t('editor.codex.editor.both'))} (${escapeHtml(book.source_language)} &amp; ${escapeHtml(book.target_language)})</option>
        `;
			}
			
			if (temperatureInputEl) {
				temperatureInputEl.value = defaultTemperature;
			}
			
			if (textareaEl) {
				textareaEl.value = book.codex_content || '';
			}
			
			// Re-bind click event triggers to prevent duplication
			const newSaveBtn = saveBtnEl.cloneNode(true);
			saveBtnEl.parentNode.replaceChild(newSaveBtn, saveBtnEl);
			newSaveBtn.addEventListener('click', () => saveCodex(book.id));
			
			const newRebuildBtn = rebuildBtnEl.cloneNode(true);
			rebuildBtnEl.parentNode.replaceChild(newRebuildBtn, rebuildBtnEl);
			newRebuildBtn.addEventListener('click', () => rebuildCodex(book.id));
			
		} catch (error) {
			if (loadingTextEl) {
				loadingTextEl.classList.remove('hidden');
				loadingTextEl.innerHTML = `<span class="text-error">Error: ${escapeHtml(error.message)}</span>`;
			}
		}
	}
	
	function getGenerationOptions (rebuild = false) {
		const model = modelSelectEl?.value || defaultModel;
		const temperature = Number(temperatureInputEl?.value || defaultTemperature);
		const codex_language = languageSelectEl?.value || activeBook?.target_language;
		
		localStorage.setItem('parallel-leaves-codex-model', model);
		localStorage.setItem('parallel-leaves-codex-temperature', String(temperature));
		
		return { model, temperature, rebuild, codex_language };
	}
	
	async function saveCodex (bookId) {
		const content = textareaEl.value;
		try {
			await window.api.saveCodex(bookId, content);
			window.showAlertModal(t('editor.codex.messages.savedSuccess'), t('editor.codex.messages.savedTitle'));
		} catch (error) {
			window.showAlertModal(t('editor.codex.messages.saveFailed', { message: error.message }), t('editor.codex.messages.saveFailedTitle'));
		}
	}
	
	async function rebuildCodex (bookId) {
		if (isGenerating) return;
		
		const processed = Number(activeBook?.codex_chunks_processed || 0);
		const total = Number(activeBook?.codex_chunks_total || 0);
		const isPartial = processed > 0 && processed < total;
		
		let rebuild = true;
		
		if (isPartial) {
			const choice = await window.showConfirmationModal(
				t('editor.codex.messages.partialFound'),
				t('editor.codex.messages.partialTitle'),
				{
					confirmText: t('editor.codex.messages.startFresh'),
					cancelText: t('common.cancel'),
					showExtra: true,
					extraText: t('editor.codex.messages.resumeBtn'),
					extraClass: 'btn btn-primary flex-1'
				}
			);
			
			if (choice === 'extra') {
				rebuild = false;
			} else if (choice === 'confirm') {
				rebuild = true;
			} else {
				return;
			}
		} else {
			const choice = await window.showConfirmationModal(
				t('editor.codex.messages.rebuildConfirm'),
				t('editor.codex.messages.rebuildTitle')
			);
			if (choice !== 'confirm') {
				return;
			}
			rebuild = true;
		}
		
		isGenerating = true;
		
		const currentRebuildBtn = document.getElementById('codex-rebuild-btn');
		const currentSaveBtn = document.getElementById('codex-save-btn');
		
		if (currentRebuildBtn) currentRebuildBtn.disabled = true;
		if (currentSaveBtn) currentSaveBtn.disabled = true;
		if (textareaEl) textareaEl.readOnly = true;
		if (progressContainerEl) progressContainerEl.classList.remove('hidden');
		
		try {
			const options = getGenerationOptions(rebuild);
			const start = await window.api.startCodex(bookId, options);
			if (start.status === 'complete') {
				if (statusEl) statusEl.textContent = t('editor.codex.statusLabel.complete');
				if (progressBarEl) progressBarEl.value = 100;
				if (progressPercentEl) progressPercentEl.textContent = '100%';
				await editCodex(bookId);
				return;
			}
			
			while (true) {
				const status = await window.api.processCodexBatch(bookId, options);
				
				const currentPercent = status.total > 0 ? Math.round((status.processed / status.total) * 100) : 0;
				if (progressBarEl) progressBarEl.value = currentPercent;
				if (progressPercentEl) progressPercentEl.textContent = `${currentPercent}%`;
				
				if (status.codex_content && textareaEl) {
					textareaEl.value = status.codex_content;
				}
				
				if (status.status === 'complete') {
					if (statusEl) statusEl.textContent = t('editor.codex.statusLabel.complete');
					break;
				}
				if (status.status === 'error') {
					throw new Error(status.error_message || 'Codex generation failed.');
				}
				if (statusEl) {
					statusEl.textContent = `Generating ${status.processed}/${status.total}`;
				}
				await new Promise(resolve => setTimeout(resolve, 250));
			}
			
			await editCodex(bookId);
		} catch (error) {
			if (statusEl) statusEl.textContent = 'Error: ' + error.message;
			window.showAlertModal(t('editor.codex.messages.generationFailed', { message: error.message }), t('editor.codex.messages.generationFailedTitle'));
		} finally {
			isGenerating = false;
			const finalRebuildBtn = document.getElementById('codex-rebuild-btn');
			const finalSaveBtn = document.getElementById('codex-save-btn');
			
			if (finalRebuildBtn) finalRebuildBtn.disabled = false;
			if (finalSaveBtn) finalSaveBtn.disabled = false;
			if (textareaEl) textareaEl.readOnly = false;
			if (progressContainerEl) progressContainerEl.classList.add('hidden');
		}
	}
	
	if (urlBookId) {
		editCodex(urlBookId);
	} else {
		window.location.href = '/dashboard';
	}
});
