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

let currentBookId;
let tmData = [];
let filteredData = [];
let currentPage = 1;
let perPage = 10;
let searchQuery = '';

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
	
	if (!tmModal) {
		console.error('TM Manager Modal element not found.');
		return;
	}
	
	applyTranslationsTo(tmModal);
	
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
}

export async function openTmModal () {
	if (!tmModal) return;
	
	try {
		currentPage = 1;
		searchQuery = '';
		if (tmSearchInput) tmSearchInput.value = '';
		
		const data = await window.api.getTmDetails(currentBookId);
		tmData = data || [];
		
		filterData();
		renderTmCards();
		tmModal.showModal();
	} catch (error) {
		console.error('Failed to open TM modal:', error);
		// MODIFIED: Localized modal initialization failure message
		window.showAlert(t('editor.translationMemory.errorOpen', { message: error.message }));
	}
}
