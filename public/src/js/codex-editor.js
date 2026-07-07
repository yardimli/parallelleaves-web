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
	const wordCountEl = document.getElementById('codex-word-count');
	const visibleWordCountEl = document.getElementById('codex-visible-word-count');
	const tokenCountEl = document.getElementById('codex-token-count');
	const styleStatusEl = document.getElementById('style-analysis-status');
	const stylePercentSliderEl = document.getElementById('style-analysis-percent-slider');
	const stylePercentValueEl = document.getElementById('style-analysis-percent-value');
	const styleRunBtnEl = document.getElementById('style-analysis-run-btn');
	const styleProgressContainerEl = document.getElementById('style-analysis-progress-container');
	const styleProgressBarEl = document.getElementById('style-analysis-progress-bar');
	const styleProgressPercentEl = document.getElementById('style-analysis-progress-percent');
	const styleTextareaEl = document.getElementById('style-analysis-textarea');
	const styleWordCountEl = document.getElementById('style-analysis-word-count');
	const styleTokenCountEl = document.getElementById('style-analysis-token-count');
	const styleSaveBtnEl = document.getElementById('style-analysis-save-btn');
	
	const escapeHtml = (value) => String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');

	function countWords (text) {
		const words = String(text || '').trim().match(/\S+/g);
		return words ? words.length : 0;
	}

	function estimateTokens (text) {
		const length = String(text || '').trim().length;
		return length > 0 ? Math.ceil(length / 4) : 0;
	}

	function updateCodexStats () {
		const text = textareaEl?.value || '';
		const words = countWords(text);
		const tokens = estimateTokens(text);
		if (wordCountEl) {
			wordCountEl.textContent = words.toLocaleString();
		}
		if (visibleWordCountEl) {
			visibleWordCountEl.textContent = words.toLocaleString();
		}
		if (tokenCountEl) {
			tokenCountEl.textContent = tokens.toLocaleString();
		}
	}

	function updateStyleStats () {
		const text = styleTextareaEl?.value || '';
		if (styleWordCountEl) {
			styleWordCountEl.textContent = countWords(text).toLocaleString();
		}
		if (styleTokenCountEl) {
			styleTokenCountEl.textContent = estimateTokens(text).toLocaleString();
		}
	}

	function modelLabel (model) {
		const inputPrice = Number(model.prompt_price_per_million);
		const outputPrice = Number(model.completion_price_per_million);
		if (!Number.isFinite(inputPrice) || !Number.isFinite(outputPrice)) {
			return model.name;
		}

		return `${model.name} ($${inputPrice.toFixed(2)} in / $${outputPrice.toFixed(2)} out per 1M)`;
	}
	
	// MODIFIED: Configured status parsing to accurately return translation lookups
	function statusLabel (book) {
		const status = book?.codex_status || 'none';
		const processed = Number(book?.codex_chunks_processed || 0);
		const total = Number(book?.codex_chunks_total || 0);
		if (total > 0 && processed >= total) return t('editor.codex.statusLabel.complete');
		if (status === 'complete') return t('editor.codex.statusLabel.complete');
		if (status === 'generating' && total > 0) {
			return t('editor.codex.statusLabel.incomplete', {
				processed,
				total
			});
		}
		if (status === 'error') return t('editor.codex.statusLabel.error');
		return t('editor.codex.statusLabel.incomplete_none');
	}

	function styleStatusLabel (book) {
		const status = book?.style_analysis_status || 'none';
		if (status === 'complete') return t('editor.codex.statusLabel.complete');
		if (status === 'generating') return t('editor.codex.style.generating');
		if (status === 'error') return t('editor.codex.statusLabel.error');
		return t('editor.codex.statusLabel.incomplete_none');
	}
	
	function modelOptionsHtml (selectedModel = defaultModel) {
		return modelData.map(group => `
      <optgroup label="${escapeHtml(group.group)}">
        ${group.models.map(model => `
          <option value="${escapeHtml(model.id)}" ${model.id === selectedModel ? 'selected' : ''}>${escapeHtml(modelLabel(model))}</option>
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
          <option value="English" selected>English</option>
          <option value="${escapeHtml(book.source_language)}">${escapeHtml(t('editor.codex.editor.source'))} (${escapeHtml(book.source_language)})</option>
          <option value="${escapeHtml(book.target_language)}">${escapeHtml(t('editor.codex.editor.target'))} (${escapeHtml(book.target_language)})</option>
          <option value="both">${escapeHtml(t('editor.codex.editor.both'))} (${escapeHtml(book.source_language)} &amp; ${escapeHtml(book.target_language)})</option>
        `;
			}
			
			if (temperatureInputEl) {
				temperatureInputEl.value = defaultTemperature;
			}
			
			if (textareaEl) {
				textareaEl.value = book.codex_content || '';
				updateCodexStats();
			}
			if (styleTextareaEl) {
				styleTextareaEl.value = book.style_analysis_content || '';
				updateStyleStats();
			}
			if (styleStatusEl) {
				styleStatusEl.textContent = styleStatusLabel(book);
			}
			if (stylePercentSliderEl) {
				stylePercentSliderEl.value = book.style_analysis_percent || 5;
				if (stylePercentValueEl) {
					stylePercentValueEl.textContent = `${stylePercentSliderEl.value}%`;
				}
			}
			
			// Re-bind click event triggers to prevent duplication
			const currentSaveBtn = document.getElementById('codex-save-btn');
			const newSaveBtn = currentSaveBtn.cloneNode(true);
			currentSaveBtn.parentNode.replaceChild(newSaveBtn, currentSaveBtn);
			newSaveBtn.addEventListener('click', () => saveCodex(book.id));
			
			const currentRebuildBtn = document.getElementById('codex-rebuild-btn');
			const newRebuildBtn = currentRebuildBtn.cloneNode(true);
			currentRebuildBtn.parentNode.replaceChild(newRebuildBtn, currentRebuildBtn);
			newRebuildBtn.addEventListener('click', () => rebuildCodex(book.id));

			const currentStyleRunBtn = document.getElementById('style-analysis-run-btn');
			const newStyleRunBtn = currentStyleRunBtn.cloneNode(true);
			currentStyleRunBtn.parentNode.replaceChild(newStyleRunBtn, currentStyleRunBtn);
			newStyleRunBtn.addEventListener('click', () => runStyleAnalysis(book.id));

			const currentStyleSaveBtn = document.getElementById('style-analysis-save-btn');
			const newStyleSaveBtn = currentStyleSaveBtn.cloneNode(true);
			currentStyleSaveBtn.parentNode.replaceChild(newStyleSaveBtn, currentStyleSaveBtn);
			newStyleSaveBtn.addEventListener('click', () => saveStyleAnalysis(book.id));
			
		} catch (error) {
			if (loadingTextEl) {
				loadingTextEl.classList.remove('hidden');
				loadingTextEl.innerHTML = `<span class="text-error">Error: ${escapeHtml(error.message)}</span>`;
			}
		}
	}

	function getStyleOptions (rebuild = true) {
		const baseOptions = getGenerationOptions(false);
		const percent = Number(document.getElementById('style-analysis-percent-slider')?.value || 5);
		return {...baseOptions, percent, rebuild};
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
			const choice = await window.showConfirmationModal(
				'Do you want to mark this codex as complete as well?',
				'Save Codex',
				{
					confirmText: 'Mark complete',
					confirmClass: 'btn btn-success flex-1',
					cancelText: 'Save only',
					cancelClass: 'btn flex-1'
				}
			);
			const markComplete = choice === 'confirm';
			const result = await window.api.saveCodex(bookId, content, {mark_complete: markComplete});
			if (markComplete) {
				activeBook = {...activeBook, codex_status: 'complete'};
				if (statusEl) statusEl.textContent = t('editor.codex.statusLabel.complete');
			} else if (result?.codex_status && activeBook) {
				activeBook = {...activeBook, codex_status: result.codex_status};
			}
			window.showAlertModal(t('editor.codex.messages.savedSuccess'), t('editor.codex.messages.savedTitle'));
		} catch (error) {
			window.showAlertModal(t('editor.codex.messages.saveFailed', { message: error.message }), t('editor.codex.messages.saveFailedTitle'));
		}
	}

	async function compactCodex (bookId, percent) {
		if (isGenerating) return;
		const content = textareaEl?.value || '';
		const wordCount = countWords(content);
		if (wordCount === 0) {
			window.showAlertModal('There is no codex content to compact.', 'Compact Codex');
			return;
		}

		const targetWordCount = Math.max(1, Math.ceil(wordCount * ((100 - percent) / 100)));
		const choice = await window.showConfirmationModal(
			`Compact the current codex from ${wordCount.toLocaleString()} words to about ${targetWordCount.toLocaleString()} words?`,
			'Compact Codex'
		);
		if (choice !== 'confirm') return;

		isGenerating = true;
		const currentSaveBtn = document.getElementById('codex-save-btn');
		const currentCompactBtn = document.getElementById('codex-compact-menu-btn');
		const compactOptions = document.querySelectorAll('.js-compact-codex-option');

		if (currentSaveBtn) currentSaveBtn.disabled = true;
		if (currentCompactBtn) currentCompactBtn.disabled = true;
		compactOptions.forEach(button => { button.disabled = true; });
		if (textareaEl) textareaEl.readOnly = true;
		if (statusEl) statusEl.textContent = `Compacting codex by ${percent}%...`;

		try {
			const result = await window.api.compactCodex(bookId, {
				...getGenerationOptions(false),
				content,
				percent
			});
			if (textareaEl) {
				textareaEl.value = result.codex_content || '';
				updateCodexStats();
			}
			activeBook = {
				...activeBook,
				codex_content: result.codex_content || ''
			};
			if (statusEl) {
				statusEl.textContent = `Compacted: ${Number(result.original_word_count || wordCount).toLocaleString()} to ${Number(result.compacted_word_count || countWords(textareaEl?.value || '')).toLocaleString()} words`;
			}
		} catch (error) {
			if (statusEl) statusEl.textContent = 'Error: ' + error.message;
			window.showAlertModal(t('editor.codex.messages.generationFailed', { message: error.message }), t('editor.codex.messages.generationFailedTitle'));
		} finally {
			isGenerating = false;
			const finalSaveBtn = document.getElementById('codex-save-btn');
			const finalCompactBtn = document.getElementById('codex-compact-menu-btn');
			const finalCompactOptions = document.querySelectorAll('.js-compact-codex-option');
			if (finalSaveBtn) finalSaveBtn.disabled = false;
			if (finalCompactBtn) finalCompactBtn.disabled = false;
			finalCompactOptions.forEach(button => { button.disabled = false; });
			if (textareaEl) textareaEl.readOnly = false;
		}
	}

	async function saveStyleAnalysis (bookId) {
		const content = styleTextareaEl.value;
		try {
			await window.api.saveStyleAnalysis(bookId, content);
			window.showAlertModal(t('editor.codex.messages.savedSuccess'), t('editor.codex.messages.savedTitle'));
		} catch (error) {
			window.showAlertModal(t('editor.codex.messages.saveFailed', { message: error.message }), t('editor.codex.messages.saveFailedTitle'));
		}
	}

	async function runStyleAnalysis (bookId) {
		if (isGenerating) return;
		const choice = await window.showConfirmationModal(
			t('editor.codex.style.confirm'),
			t('editor.codex.style.title')
		);
		if (choice !== 'confirm') return;

		isGenerating = true;
		const currentRunBtn = document.getElementById('style-analysis-run-btn');
		const currentSaveBtn = document.getElementById('style-analysis-save-btn');
		if (currentRunBtn) currentRunBtn.disabled = true;
		if (currentSaveBtn) currentSaveBtn.disabled = true;
		if (styleTextareaEl) styleTextareaEl.readOnly = true;
		if (styleProgressContainerEl) styleProgressContainerEl.classList.remove('hidden');

		try {
			const options = getStyleOptions(true);
			const start = await window.api.startStyleAnalysis(bookId, options);
			if (start.status === 'complete') {
				if (styleStatusEl) styleStatusEl.textContent = t('editor.codex.statusLabel.complete');
				if (styleProgressBarEl) styleProgressBarEl.value = 100;
				if (styleProgressPercentEl) styleProgressPercentEl.textContent = '100%';
				await editCodex(bookId);
				return;
			}

			if (styleStatusEl) styleStatusEl.textContent = t('editor.codex.style.generating');
			if (styleProgressBarEl) styleProgressBarEl.value = 25;
			if (styleProgressPercentEl) styleProgressPercentEl.textContent = '25%';

			const status = await window.api.processStyleAnalysisBatch(bookId, options);
			if (status.status === 'error') {
				throw new Error(status.error_message || 'Style analysis failed.');
			}
			if (status.style_analysis_content && styleTextareaEl) {
				styleTextareaEl.value = status.style_analysis_content;
				updateStyleStats();
			}
			if (styleProgressBarEl) styleProgressBarEl.value = 100;
			if (styleProgressPercentEl) styleProgressPercentEl.textContent = '100%';
			if (styleStatusEl) styleStatusEl.textContent = t('editor.codex.statusLabel.complete');

			await editCodex(bookId);
		} catch (error) {
			if (styleStatusEl) styleStatusEl.textContent = 'Error: ' + error.message;
			window.showAlertModal(t('editor.codex.messages.generationFailed', { message: error.message }), t('editor.codex.messages.generationFailedTitle'));
		} finally {
			isGenerating = false;
			const finalRunBtn = document.getElementById('style-analysis-run-btn');
			const finalSaveBtn = document.getElementById('style-analysis-save-btn');
			if (finalRunBtn) finalRunBtn.disabled = false;
			if (finalSaveBtn) finalSaveBtn.disabled = false;
			if (styleTextareaEl) styleTextareaEl.readOnly = false;
			if (styleProgressContainerEl) styleProgressContainerEl.classList.add('hidden');
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
					updateCodexStats();
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

	stylePercentSliderEl?.addEventListener('input', () => {
		if (stylePercentValueEl) {
			stylePercentValueEl.textContent = `${stylePercentSliderEl.value}%`;
		}
	});

	textareaEl?.addEventListener('input', updateCodexStats);
	styleTextareaEl?.addEventListener('input', updateStyleStats);

	document.querySelectorAll('.js-compact-codex-option').forEach(button => {
		button.addEventListener('click', () => {
			const percent = Number(button.dataset.percent || 25);
			if (activeBook?.id) {
				compactCodex(activeBook.id, percent);
			}
		});
	});
});
