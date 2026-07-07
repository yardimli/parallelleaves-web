// NEW JS FILE: Controls the inside-editor dialog interface for managing translation memory entries
import { t, applyTranslationsTo } from '../i18n.js';

let tmModal;
let tmCardsContainer;
let tmNoEntriesMessage;
let tmSearchInput;
let tmPerPageSelect;
let tmPrevPageBtn;
let tmNextPageBtn;
let tmPaginationInfo;
let tmOpenPurgeBtn;
let tmPurgeDialog;
let tmPurgeRuleInput;
let tmPurgeModelSelect;
let tmPurgeStartBtn;
let tmPurgeCancelBtn;
let tmPurgeCloseBtn;
let tmPurgeProgressContainer;
let tmPurgeProgressBar;
let tmPurgeProgressCount;
let tmPurgeResultText;

let currentBookId;
let tmData = [];
let filteredData = [];
let currentPage = 1;
let perPage = 10;
let searchQuery = '';
let isPurgeRunning = false;

function modelOptionLabel (model) {
	const inputPrice = Number(model.prompt_price_per_million);
	const outputPrice = Number(model.completion_price_per_million);
	const priceLabel = Number.isFinite(inputPrice) && Number.isFinite(outputPrice)
		? ` ($${inputPrice.toFixed(2)} in / $${outputPrice.toFixed(2)} out per 1M)`
		: '';
	return `${model.name || model.id}${priceLabel}`;
}

function populatePurgeModelSelect () {
	if (!tmPurgeModelSelect) return;
	const modelGroups = window.initialModels?.models || [];
	const savedModel = localStorage.getItem('parallel-leaves-ai-model') || localStorage.getItem('parallel-leaves-codex-model') || '';
	let hasOptions = false;
	
	tmPurgeModelSelect.innerHTML = '';
	modelGroups.forEach(group => {
		const optgroup = document.createElement('optgroup');
		optgroup.label = group.group || group.provider || group.name || 'Models';
		(group.models || []).forEach(model => {
			const option = document.createElement('option');
			option.value = model.id;
			option.textContent = modelOptionLabel(model);
			if (savedModel && model.id === savedModel) {
				option.selected = true;
			}
			optgroup.appendChild(option);
			hasOptions = true;
		});
		if (optgroup.children.length > 0) {
			tmPurgeModelSelect.appendChild(optgroup);
		}
	});
	
	if (!hasOptions) {
		const option = document.createElement('option');
		option.value = 'openai/gpt-4o-mini';
		option.textContent = 'openai/gpt-4o-mini';
		option.selected = true;
		tmPurgeModelSelect.appendChild(option);
	}
}

function setPurgeProgress (processed, total, deleted) {
	const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
	if (tmPurgeProgressBar) tmPurgeProgressBar.value = percent;
	if (tmPurgeProgressCount) tmPurgeProgressCount.textContent = `${processed} / ${total}`;
	if (tmPurgeResultText) tmPurgeResultText.textContent = `Deleted ${deleted} of ${total} entries.`;
}

async function refreshTmData () {
	const data = await window.api.getTmDetails(currentBookId);
	tmData = data || [];
	filterData();
	renderTmCards();
}

async function startPurge () {
	if (isPurgeRunning) return;
	const rule = (tmPurgeRuleInput?.value || '').trim();
	const model = tmPurgeModelSelect?.value || '';
	if (!rule) {
		window.showAlert('Enter a purge rule first.');
		return;
	}
	if (!model) {
		window.showAlert('Choose a model first.');
		return;
	}
	if (tmData.length === 0) {
		window.showAlert('There are no translation memory entries to purge.');
		return;
	}
	if (!confirm(`Check ${tmData.length} translation memory entries and delete rows that match this rule?`)) {
		return;
	}
	
	isPurgeRunning = true;
	localStorage.setItem('parallel-leaves-ai-model', model);
	if (tmPurgeStartBtn) tmPurgeStartBtn.disabled = true;
	if (tmPurgeCancelBtn) tmPurgeCancelBtn.disabled = true;
	if (tmPurgeCloseBtn) tmPurgeCloseBtn.disabled = true;
	if (tmPurgeProgressContainer) tmPurgeProgressContainer.classList.remove('hidden');
	setPurgeProgress(0, tmData.length, 0);
	
	let processed = 0;
	let deleted = 0;
	const entries = [...tmData];
	
	try {
		for (const entry of entries) {
			const result = await window.api.purgeTmRow(entry.id, rule, model);
			processed++;
			if (result?.deleted) {
				deleted++;
				tmData = tmData.filter(item => item.id !== entry.id);
			}
			setPurgeProgress(processed, entries.length, deleted);
		}
		
		await refreshTmData();
		if (tmPurgeResultText) tmPurgeResultText.textContent = `Purge complete. Deleted ${deleted} of ${entries.length} entries.`;
	} catch (error) {
		console.error('Failed to purge translation memory:', error);
		window.showAlert(`Failed to purge translation memory: ${error.message}`);
		if (tmPurgeResultText) tmPurgeResultText.textContent = `Stopped after ${processed} of ${entries.length}. Deleted ${deleted}.`;
	} finally {
		isPurgeRunning = false;
		if (tmPurgeStartBtn) tmPurgeStartBtn.disabled = false;
		if (tmPurgeCancelBtn) tmPurgeCancelBtn.disabled = false;
		if (tmPurgeCloseBtn) tmPurgeCloseBtn.disabled = false;
	}
}

function openPurgeDialog () {
	if (!tmPurgeDialog) {
		window.showAlert('Purge dialog is not available on this page.');
		return;
	}
	populatePurgeModelSelect();
	if (tmPurgeProgressContainer) tmPurgeProgressContainer.classList.add('hidden');
	if (tmPurgeProgressBar) tmPurgeProgressBar.value = 0;
	if (tmPurgeProgressCount) tmPurgeProgressCount.textContent = `0 / ${tmData.length}`;
	if (tmPurgeResultText) tmPurgeResultText.textContent = '';
	
	try {
		if (tmModal?.open) {
			tmModal.close();
		}
		tmPurgeDialog.showModal();
	} catch (error) {
		console.error('Failed to open TM purge dialog:', error);
		window.showAlert(`Failed to open purge dialog: ${error.message}`);
	}
}

function reopenTmModalAfterPurge () {
	if (isPurgeRunning || !tmModal || tmModal.open) {
		return;
	}
	tmModal.showModal();
}

// Filters translation memory matching against both target and source segments
function filterData () {
	if (!searchQuery) {
		filteredData = [...tmData];
	} else {
		const query = searchQuery.toLowerCase();
		filteredData = tmData.filter(entry =>
			(entry.source_sentence || '').toLowerCase().includes(query) ||
			(entry.original_target_sentence || '').toLowerCase().includes(query) ||
			(entry.edited_target_sentence || '').toLowerCase().includes(query)
		);
	}
	
	const totalPages = Math.ceil(filteredData.length / perPage) || 1;
	if (currentPage > totalPages) {
		currentPage = totalPages;
	}
}

// Renders list segments one under the other with inline textarea editing
function renderTmCards () {
	tmCardsContainer.innerHTML = '';
	
	if (filteredData.length === 0) {
		tmNoEntriesMessage.classList.remove('hidden');
		// MODIFIED: Localized empty page indicator
		tmPaginationInfo.textContent = t('editor.translationMemory.pageInfo', { current: 1, total: 1 });
		tmPrevPageBtn.disabled = true;
		tmNextPageBtn.disabled = true;
		return;
	}
	
	tmNoEntriesMessage.classList.add('hidden');
	
	const totalPages = Math.ceil(filteredData.length / perPage) || 1;
	// MODIFIED: Localized pagination indicator
	tmPaginationInfo.textContent = t('editor.translationMemory.pageInfo', { current: currentPage, total: totalPages });
	tmPrevPageBtn.disabled = currentPage === 1;
	tmNextPageBtn.disabled = currentPage === totalPages;
	
	const start = (currentPage - 1) * perPage;
	const end = start + perPage;
	const pageData = filteredData.slice(start, end);
	
	pageData.forEach(entry => {
		const card = document.createElement('div');
		card.className = 'card bg-base-200 shadow-sm border border-base-300 p-4 relative';
		card.dataset.id = entry.id;
		
		// Helper to safely escape raw segment database presentation
		const escape = (str) => {
			return String(str ?? '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		};
		
		// Segments are shown stacked directly under each other
		card.innerHTML = `
      <div class="flex justify-between items-start gap-4 mb-2">
        <span class="badge badge-sm badge-neutral font-mono">ID: ${entry.id}</span>
        <div class="flex gap-1">
          <button type="button" class="js-tm-edit-btn btn btn-ghost btn-xs btn-square" title="Edit"><i class="bi bi-pencil"></i></button>
          <button type="button" class="js-tm-save-btn btn btn-success btn-xs btn-square hidden" title="Save"><i class="bi bi-check-lg"></i></button>
          <button type="button" class="js-tm-cancel-btn btn btn-ghost btn-xs btn-square hidden" title="Cancel"><i class="bi bi-x-lg"></i></button>
          <button type="button" class="js-tm-delete-btn btn btn-ghost btn-xs btn-square text-error" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>
      <div class="space-y-2">
        <div>
          <div class="text-xs font-bold text-base-content/50 uppercase">Source:</div>
          <div class="js-tm-source-text font-mono whitespace-pre-wrap leading-relaxed text-sm">${escape(entry.source_sentence)}</div>
          <textarea class="js-tm-source-input textarea textarea-bordered textarea-sm w-full font-mono hidden mt-1">${escape(entry.source_sentence)}</textarea>
        </div>
        <div class="border-t border-base-300/50 pt-2">
          <div class="text-xs font-bold text-base-content/50 uppercase">Original Target:</div>
          <div class="js-tm-original-target-text font-mono whitespace-pre-wrap leading-relaxed text-sm">${escape(entry.original_target_sentence)}</div>
          <textarea class="js-tm-original-target-input textarea textarea-bordered textarea-sm w-full font-mono hidden mt-1">${escape(entry.original_target_sentence)}</textarea>
        </div>
        <div class="border-t border-base-300/50 pt-2">
          <div class="text-xs font-bold text-base-content/50 uppercase">Edited Target:</div>
          <div class="js-tm-edited-target-text font-mono whitespace-pre-wrap leading-relaxed text-sm">${escape(entry.edited_target_sentence)}</div>
          <textarea class="js-tm-edited-target-input textarea textarea-bordered textarea-sm w-full font-mono hidden mt-1">${escape(entry.edited_target_sentence)}</textarea>
        </div>
      </div>
    `;
		
		const editBtn = card.querySelector('.js-tm-edit-btn');
		const saveBtn = card.querySelector('.js-tm-save-btn');
		const cancelBtn = card.querySelector('.js-tm-cancel-btn');
		const deleteBtn = card.querySelector('.js-tm-delete-btn');
		
		const sourceTextEl = card.querySelector('.js-tm-source-text');
		const sourceInputEl = card.querySelector('.js-tm-source-input');
		const originalTargetTextEl = card.querySelector('.js-tm-original-target-text');
		const originalTargetInputEl = card.querySelector('.js-tm-original-target-input');
		const editedTargetTextEl = card.querySelector('.js-tm-edited-target-text');
		const editedTargetInputEl = card.querySelector('.js-tm-edited-target-input');
		
		editBtn.addEventListener('click', () => {
			editBtn.classList.add('hidden');
			deleteBtn.classList.add('hidden');
			saveBtn.classList.remove('hidden');
			cancelBtn.classList.remove('hidden');
			
			sourceTextEl.classList.add('hidden');
			sourceInputEl.classList.remove('hidden');
			originalTargetTextEl.classList.add('hidden');
			originalTargetInputEl.classList.remove('hidden');
			editedTargetTextEl.classList.add('hidden');
			editedTargetInputEl.classList.remove('hidden');
		});
		
		cancelBtn.addEventListener('click', () => {
			editBtn.classList.remove('hidden');
			deleteBtn.classList.remove('hidden');
			saveBtn.classList.add('hidden');
			cancelBtn.classList.add('hidden');
			
			sourceTextEl.classList.remove('hidden');
			sourceInputEl.classList.add('hidden');
			originalTargetTextEl.classList.remove('hidden');
			originalTargetInputEl.classList.add('hidden');
			editedTargetTextEl.classList.remove('hidden');
			editedTargetInputEl.classList.add('hidden');
			
			sourceInputEl.value = entry.source_sentence;
			originalTargetInputEl.value = entry.original_target_sentence || '';
			editedTargetInputEl.value = entry.edited_target_sentence || '';
		});
		
		saveBtn.addEventListener('click', async () => {
			const newSource = sourceInputEl.value.trim();
			const newOriginalTarget = originalTargetInputEl.value.trim();
			const newEditedTarget = editedTargetInputEl.value.trim();
			
			try {
				await window.api.updateTmRow(entry.id, newSource, newOriginalTarget, newEditedTarget);
				
				entry.source_sentence = newSource;
				entry.original_target_sentence = newOriginalTarget;
				entry.edited_target_sentence = newEditedTarget;
				
				filterData();
				renderTmCards();
			} catch (err) {
				console.error('Failed to update TM segment:', err);
				// MODIFIED: Localized segment update failure message
				window.showAlert(t('editor.translationMemory.errorUpdate', { message: err.message }));
			}
		});
		
		deleteBtn.addEventListener('click', async () => {
			// MODIFIED: Localized segment deletion confirmation prompt
			if (confirm(t('editor.translationMemory.confirmDelete'))) {
				try {
					await window.api.deleteTmRow(entry.id);
					
					tmData = tmData.filter(item => item.id !== entry.id);
					
					filterData();
					renderTmCards();
				} catch (err) {
					console.error('Failed to delete TM segment:', err);
					// MODIFIED: Localized segment deletion failure message
					window.showAlert(t('editor.translationMemory.errorDelete', { message: err.message }));
				}
			}
		});
		
		tmCardsContainer.appendChild(card);
	});
}

export function initTmModal (bookId) {
	currentBookId = bookId;
	tmModal = document.getElementById('tm-manager-modal');
	tmCardsContainer = document.getElementById('tm-cards-container');
	tmNoEntriesMessage = document.getElementById('tm-no-entries');
	tmSearchInput = document.getElementById('tm-search-input');
	tmPerPageSelect = document.getElementById('tm-per-page-select');
	tmPrevPageBtn = document.getElementById('tm-prev-page-btn');
	tmNextPageBtn = document.getElementById('tm-next-page-btn');
	tmPaginationInfo = document.getElementById('tm-pagination-info');
	tmOpenPurgeBtn = document.getElementById('tm-open-purge-btn');
	tmPurgeDialog = document.getElementById('tm-purge-dialog');
	tmPurgeRuleInput = document.getElementById('tm-purge-rule-input');
	tmPurgeModelSelect = document.getElementById('tm-purge-model-select');
	tmPurgeStartBtn = document.getElementById('tm-purge-start-btn');
	tmPurgeCancelBtn = document.getElementById('tm-purge-cancel-btn');
	tmPurgeCloseBtn = document.getElementById('tm-purge-close-btn');
	tmPurgeProgressContainer = document.getElementById('tm-purge-progress-container');
	tmPurgeProgressBar = document.getElementById('tm-purge-progress-bar');
	tmPurgeProgressCount = document.getElementById('tm-purge-progress-count');
	tmPurgeResultText = document.getElementById('tm-purge-result-text');
	
	if (!tmModal) {
		console.error('TM Manager Modal element not found.');
		return;
	}
	
	applyTranslationsTo(tmModal);
	if (tmPurgeDialog) {
		applyTranslationsTo(tmPurgeDialog);
	}
	
	tmSearchInput.addEventListener('input', () => {
		searchQuery = tmSearchInput.value;
		currentPage = 1;
		filterData();
		renderTmCards();
	});
	
	tmPerPageSelect.addEventListener('change', () => {
		perPage = parseInt(tmPerPageSelect.value, 10);
		currentPage = 1;
		filterData();
		renderTmCards();
	});
	
	tmPrevPageBtn.addEventListener('click', () => {
		if (currentPage > 1) {
			currentPage--;
			renderTmCards();
		}
	});
	
	tmNextPageBtn.addEventListener('click', () => {
		const totalPages = Math.ceil(filteredData.length / perPage) || 1;
		if (currentPage < totalPages) {
			currentPage++;
			renderTmCards();
		}
	});

	populatePurgeModelSelect();
	tmOpenPurgeBtn?.addEventListener('click', openPurgeDialog);
	tmPurgeDialog?.addEventListener('close', reopenTmModalAfterPurge);
	tmPurgeStartBtn?.addEventListener('click', startPurge);
}

export async function openTmModal () {
	if (!tmModal) return;
	
	try {
		currentPage = 1;
		searchQuery = '';
		if (tmSearchInput) tmSearchInput.value = '';
		
		await refreshTmData();
		tmModal.showModal();
	} catch (error) {
		console.error('Failed to open TM modal:', error);
		// MODIFIED: Localized modal initialization failure message
		window.showAlert(t('editor.translationMemory.errorOpen', { message: error.message }));
	}
}
