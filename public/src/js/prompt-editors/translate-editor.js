import {t, applyTranslationsTo} from '../i18n.js';
import {htmlToPlainText} from '../../utils/html-processing.js';

// Add debounce utility
const debounce = (func, delay) => {
	let timeout;
	return function (...args) {
		const context = this;
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(context, args), delay);
	};
};

const defaultState = { // Default state for the translate editor form
	instructions: '',
	includeInstructions: true,
	tense: 'past',
	contextPairs: 4,
	translationMemoryIds: []
};


const buildTranslationContextBlock = (translationPairs, languageForPrompt, targetLanguage) => {
	if (!translationPairs || translationPairs.length === 0) {
		return [];
	}
	
	const contextMessages = [];
	translationPairs.forEach(pair => {
		const sourceText = htmlToPlainText(pair.source || '');
		const targetText = htmlToPlainText(pair.target || '');
		
		if (sourceText && targetText) {
			contextMessages.push({
				role: 'user',
				content: t('prompt.translate.user.textToTranslate', {
					sourceLanguage: languageForPrompt,
					targetLanguage: targetLanguage,
					text: sourceText
				})
			});
			contextMessages.push({
				role: 'assistant',
				content: targetText
			});
		}
	});
	
	return contextMessages;
};

export const buildPromptJson = (formData, context, userDictionary = '') => {
	const {selectedText, languageForPrompt, targetLanguage, translationPairs} = context;
	
	const plainTextToTranslate = selectedText;
	const tenseInstruction = formData.tense && formData.tense !== 'none'
		? `Translate in the ${formData.tense} tense.`
		: '';
	const instructions = formData.includeInstructions === false ? '' : (formData.instructions || '').trim();
	const instructionsBlock = instructions ? `Follow these specific instructions: ${instructions}` : '';
	
	const system = t('prompt.translate.system.base', {
		sourceLanguage: languageForPrompt,
		targetLanguage: targetLanguage,
		instructionsBlock,
		tenseInstruction,
		dictionary: userDictionary
	}).trim();
	
	const contextMessages = buildTranslationContextBlock(translationPairs, languageForPrompt, targetLanguage);
	
	const finalUserPromptParts = [];
	finalUserPromptParts.push(t('prompt.translate.user.textToTranslate', {
		sourceLanguage: languageForPrompt,
		targetLanguage: targetLanguage,
		text: plainTextToTranslate
	}));
	const finalUserPrompt = finalUserPromptParts.filter(Boolean).join('\n\n');
	
	return {
		system,
		context_pairs: contextMessages,
		user: finalUserPrompt,
		ai: ''
	};
};

async function expandSystemPlaceholders(system, context, translationPairs) {
	const details = context.bookId ? await window.api.getCodexDetails(context.bookId) : null;
	const tmBlock = translationPairs && translationPairs.length > 0
		? `Use the following translation examples to guide the translation:\n${translationPairs.map(pair => {
			const sourceText = htmlToPlainText(pair.source || '');
			const targetText = htmlToPlainText(pair.target || '');
			return `<${context.languageForPrompt}>${sourceText}</${context.languageForPrompt}>\n<${context.targetLanguage}>${targetText}</${context.targetLanguage}>`;
		}).join('\n')}`
		: '';
	const styleBlock = details?.style_analysis_content
		? `Use the following source style analysis and translation guidance before glossary/codex instructions:\n<style_analysis>\n${details.style_analysis_content}\n</style_analysis>`
		: '';
	const codexBlock = details?.codex_content
		? `Use the following glossary for consistent translation:\n<glossary>\n${details.codex_content}\n</glossary>`
		: '';
	
	return system
		.replace('##TRANSLATION_MEMORY##', tmBlock)
		.replace('##STYLE_ANALYSIS_BLOCK##', styleBlock)
		.replace('##CODEX_BLOCK##', codexBlock)
		.replace(/\n{3,}/g, '\n\n')
		.trim();
}

const updatePreview = async (container, context) => {
	const form = container.querySelector('#translate-editor-form');
	if (!form) {
		return;
	}
	
	// MODIFIED: Removed logic for getting selected memory IDs
	const formData = {
		instructions: form.elements.instructions.value.trim(),
		includeInstructions: form.elements.include_instructions?.checked !== false,
		tense: form.elements.tense.value,
		contextPairs: parseInt(form.elements.context_pairs.value, 10) || 0
	};
	
	const systemPreview = container.querySelector('.js-preview-system');
	const userPreview = container.querySelector('.js-preview-user');
	const aiPreview = container.querySelector('.js-preview-ai');
	const contextPairsContainer = container.querySelector('.js-preview-context-pairs');
	
	if (!systemPreview || !userPreview || !aiPreview || !contextPairsContainer) {
		return;
	}
	
	const previewContext = {...context, translationPairs: []};
	
	if (formData.contextPairs > 0 && context.chapterId) {
		try {
			const pairs = await window.api.getTranslationContext({
				chapterId: context.chapterId,
				pairCount: formData.contextPairs,
				selectedText: context.selectedText
			});
			previewContext.translationPairs = pairs;
		} catch (error) {
			console.error('Failed to fetch translation context for preview:', error);
			userPreview.textContent = `Error fetching context: ${error.message}`;
			return;
		}
	}
	
	let userDictionaryContent = await window.api.getDictionaryContentForAI(context.bookId, 'translation');
	
	try {
		const promptJson = buildPromptJson(formData, previewContext, userDictionaryContent);
		systemPreview.textContent = container.dataset.expandPlaceholders === 'true'
			? await expandSystemPlaceholders(promptJson.system, previewContext, previewContext.translationPairs)
			: promptJson.system;
		userPreview.textContent = promptJson.user;
		aiPreview.textContent = promptJson.ai || t('prompt.preview.empty');
		
		contextPairsContainer.innerHTML = '';
		if (promptJson.context_pairs && promptJson.context_pairs.length > 0) {
			promptJson.context_pairs.forEach((message, index) => {
				const pairNumber = Math.floor(index / 2) + 1;
				const roleTitle = message.role === 'user' ? t('prompt.preview.contextUser', {number: pairNumber}) : t('prompt.preview.contextAssistant', {number: pairNumber});
				
				const title = document.createElement('h3');
				title.className = 'text-lg font-semibold mt-4 font-mono';
				title.textContent = roleTitle;
				title.classList.add(message.role === 'user' ? 'text-info' : 'text-accent');
				
				const pre = document.createElement('pre');
				pre.className = 'bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono';
				const code = document.createElement('code');
				code.textContent = message.content;
				pre.appendChild(code);
				
				contextPairsContainer.appendChild(title);
				contextPairsContainer.appendChild(pre);
			});
		}
	} catch (error) {
		systemPreview.textContent = `Error building preview: ${error.message}`;
		userPreview.textContent = '';
		aiPreview.textContent = '';
		contextPairsContainer.innerHTML = '';
	}
};

const populateForm = (container, state, bookId) => {
	const form = container.querySelector('#translate-editor-form');
	if (!form) {
		return;
	}
	
	const storageKey = `tense-preference-${bookId}-translate`;
	const savedTense = localStorage.getItem(storageKey);
	
	const tense = state.tense || savedTense || defaultState.tense;
	
	form.elements.instructions.value = state.instructions || '';
	if (form.elements.include_instructions) {
		form.elements.include_instructions.checked = state.includeInstructions !== false;
	}
	form.elements.context_pairs.value = state.contextPairs !== undefined ? state.contextPairs : 4;
	
	form.elements.tense.value = tense;
	const tenseButtons = form.querySelectorAll('.js-tense-btn');
	tenseButtons.forEach(btn => {
		btn.classList.toggle('btn-active', btn.dataset.tense === tense);
	});
};

// MODIFIED: Removed the populateTranslationMemoriesDropdown function entirely.

export const init = async (container, context) => {
	try {
		const templateHtml = document.getElementById('template-prompt-translate-editor')?.innerHTML || '';
		if (!templateHtml) {
			throw new Error('Translate editor template is missing.');
		}
		container.innerHTML = templateHtml;
		applyTranslationsTo(container);
		
		const fullContext = {...context};
		
		populateForm(container, context.initialState || defaultState, context.bookId);
		// MODIFIED: Removed call to populateTranslationMemoriesDropdown
		
		const form = container.querySelector('#translate-editor-form');
		const expandPlaceholdersBtn = container.querySelector('.js-expand-placeholders-btn');
		const copyStyleAnalysisBtn = container.querySelector('.js-copy-style-analysis-btn');
		
		const debouncedUpdatePreview = debounce(() => {
			updatePreview(container, fullContext);
		}, 500);
		
		if (form) {
			form.addEventListener('input', debouncedUpdatePreview);
			
			// MODIFIED: Removed event listener for the now-deleted select element
			
			form.addEventListener('change', (e) => {
				if (e.target.type === 'checkbox') {
					debouncedUpdatePreview();
				}
			});
			
			const tenseGroup = form.querySelector('.js-tense-group');
			if (tenseGroup) {
				tenseGroup.addEventListener('click', (e) => {
					const button = e.target.closest('.js-tense-btn');
					if (!button) {
						return;
					}
					
					const newTense = button.dataset.tense;
					
					tenseGroup.querySelectorAll('.js-tense-btn').forEach(btn => btn.classList.remove('btn-active'));
					button.classList.add('btn-active');
					
					form.elements.tense.value = newTense;
					
					const storageKey = `tense-preference-${context.bookId}-translate`;
					if (newTense === 'none') {
						localStorage.removeItem(storageKey);
					} else {
						localStorage.setItem(storageKey, newTense);
					}
					
					debouncedUpdatePreview();
				});
			}
		}

		if (expandPlaceholdersBtn) {
			expandPlaceholdersBtn.addEventListener('click', () => {
				const isExpanded = container.dataset.expandPlaceholders === 'true';
				container.dataset.expandPlaceholders = isExpanded ? 'false' : 'true';
				expandPlaceholdersBtn.textContent = isExpanded ? t('prompt.preview.expandPlaceholders') : t('prompt.preview.collapsePlaceholders');
				updatePreview(container, fullContext);
			});
		}

		if (copyStyleAnalysisBtn) {
			copyStyleAnalysisBtn.addEventListener('click', async () => {
				const details = await window.api.getCodexDetails(context.bookId);
				const styleAnalysis = details?.style_analysis_content || '';
				if (!styleAnalysis) {
					return;
				}
				const form = container.querySelector('#translate-editor-form');
				form.elements.instructions.value = styleAnalysis;
				if (form.elements.include_instructions) {
					form.elements.include_instructions.checked = true;
				}
				updatePreview(container, fullContext);
			});
		}
		
		await updatePreview(container, fullContext);
	} catch (error) {
		container.innerHTML = `<p class="p-4 text-error">${t('prompt.errorLoadForm')}</p>`;
		console.error(error);
	}
};
