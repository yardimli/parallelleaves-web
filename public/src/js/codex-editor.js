import {initI18n} from './i18n.js';

document.addEventListener('DOMContentLoaded', async () => {
	await initI18n();
	
	const container = document.getElementById('codex-container');
	const urlBookId = window.routeParams?.bookId || null;
	const modelData = window.initialModels?.models || [];
	const defaultModel = localStorage.getItem('parallel-leaves-codex-model') || 'openai/gpt-5.4';
	const defaultTemperature = localStorage.getItem('parallel-leaves-codex-temperature') || '0.5';
	let activeBook = null;
	let isGenerating = false;
	
	const escapeHtml = (value) => String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
	
	function statusLabel(book) {
		const status = book?.codex_status || 'none';
		if (status === 'complete') return 'Complete';
		if (status === 'generating' && Number(book?.codex_chunks_total || 0) > 0) {
			return `Incomplete (${Number(book.codex_chunks_processed || 0)}/${Number(book.codex_chunks_total || 0)})`;
		}
		if (status === 'error') return 'Error';
		return 'Incomplete';
	}
	
	function modelOptionsHtml(selectedModel = defaultModel) {
		return modelData.map(group => `
			<optgroup label="${escapeHtml(group.group)}">
				${group.models.map(model => `
					<option value="${escapeHtml(model.id)}" ${model.id === selectedModel ? 'selected' : ''}>${escapeHtml(model.name)}</option>
				`).join('')}
			</optgroup>
		`).join('');
	}
	
	async function loadList() {
		container.innerHTML = '<p>Loading books...</p>';
		try {
			const books = await window.api.getCodexBooks();
			if (!books || books.length === 0) {
				container.innerHTML = '<p>You have not synced any books yet.</p>';
				return;
			}
			
			container.innerHTML = `
				<table class="table w-full">
					<thead>
					<tr>
						<th>Title</th>
						<th>Languages</th>
						<th>Codex Status</th>
						<th>Actions</th>
					</tr>
					</thead>
					<tbody>
					${books.map(book => `
						<tr>
							<td>
								<div class="font-bold">${escapeHtml(book.title)}</div>
								<div class="text-sm opacity-50">${escapeHtml(book.author || 'Unknown Author')}</div>
							</td>
							<td>
								<span class="badge badge-ghost">${escapeHtml(book.source_language)}</span>
								<span>→</span>
								<span class="badge badge-ghost">${escapeHtml(book.target_language)}</span>
							</td>
							<td><span class="badge ${book.codex_status === 'complete' ? 'badge-success' : 'badge-warning'}">${escapeHtml(statusLabel(book))}</span></td>
							<td class="flex gap-2">
								<button class="btn btn-sm btn-primary js-edit-codex" data-book-id="${book.id}">Edit Codex</button>
								<button class="btn btn-sm btn-outline btn-warning js-rebuild-codex" data-book-id="${book.id}">Rebuild</button>
								<button class="btn btn-sm btn-outline btn-error js-reset-codex" data-book-id="${book.id}" ${book.codex_status === 'none' ? 'disabled' : ''}>Reset</button>
							</td>
						</tr>
					`).join('')}
					</tbody>
				</table>
			`;
			
			container.querySelectorAll('.js-edit-codex').forEach(button => {
				button.addEventListener('click', () => editCodex(button.dataset.bookId));
			});
			container.querySelectorAll('.js-rebuild-codex').forEach(button => {
				button.addEventListener('click', () => editCodex(button.dataset.bookId));
			});
			container.querySelectorAll('.js-reset-codex').forEach(button => {
				button.addEventListener('click', () => resetCodex(button.dataset.bookId));
			});
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error: ${escapeHtml(error.message)}</p>`;
		}
	}
	
	async function editCodex(bookId) {
		container.innerHTML = '<p>Loading details...</p>';
		try {
			const book = await window.api.getCodexDetails(bookId);
			if (!book) throw new Error('Book not found.');
			activeBook = book;
			
			const backActionLabel = urlBookId ? 'Back to Dashboard' : 'Back';
			
			// MODIFIED: Added Codex Language dropdown option set & integrated hidden progressbar template markup
			container.innerHTML = `
				<div class="mb-4 flex items-center justify-between gap-3">
					<button id="codex-back-btn" class="btn btn-sm btn-outline">← ${backActionLabel}</button>
					<span id="codex-generation-status" class="text-sm text-base-content/70">${escapeHtml(statusLabel(book))}</span>
				</div>
				<h2 class="text-2xl font-semibold mb-4">Editing Codex for: <span class="italic">${escapeHtml(book.title)}</span></h2>
				<div class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto_auto] gap-3 mb-4 items-end">
					<label class="form-control">
						<span class="label-text">Model</span>
						<select id="codex-model-select" class="select select-bordered">
							${modelOptionsHtml(defaultModel)}
						</select>
					</label>
					<label class="form-control w-52">
						<span class="label-text">Codex Language</span>
						<select id="codex-language-select" class="select select-bordered">
							<option value="${escapeHtml(book.source_language)}">Source (${escapeHtml(book.source_language)})</option>
							<option value="${escapeHtml(book.target_language)}" selected>Target (${escapeHtml(book.target_language)})</option>
						</select>
					</label>
					<label class="form-control w-40">
						<span class="label-text">Temperature</span>
						<input id="codex-temperature-input" type="number" min="0" max="2" step="0.1" class="input input-bordered" value="${escapeHtml(defaultTemperature)}">
					</label>
					<button id="codex-rebuild-btn" class="btn btn-warning">Rebuild Codex</button>
				</div>
				
				<!-- NEW: Progressbar template container -->
				<div id="codex-progress-container" class="hidden mb-4 space-y-1">
					<div class="flex justify-between text-xs font-semibold text-base-content/70">
						<span>Generating Codex...</span>
						<span id="codex-progress-percent">0%</span>
					</div>
					<progress id="codex-progress-bar" class="progress progress-primary w-full" value="0" max="100"></progress>
				</div>

				<div class="form-control">
					<label class="label"><span class="label-text">Codex Plain Text Content</span></label>
					<textarea id="codex-textarea" class="textarea textarea-bordered w-full h-96 font-mono">${escapeHtml(book.codex_content || '')}</textarea>
				</div>
				<div class="form-control mt-6">
					<button id="codex-save-btn" class="btn btn-success">Save Codex</button>
				</div>
			`;
			
			document.getElementById('codex-back-btn').addEventListener('click', () => {
				if (urlBookId) {
					window.location.href = '/dashboard';
				} else {
					loadList();
				}
			});
			document.getElementById('codex-save-btn').addEventListener('click', () => saveCodex(book.id));
			document.getElementById('codex-rebuild-btn').addEventListener('click', () => rebuildCodex(book.id));
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error: ${escapeHtml(error.message)}</p>`;
		}
	}
	
	function getGenerationOptions(rebuild = false) {
		const modelSelect = document.getElementById('codex-model-select');
		const temperatureInput = document.getElementById('codex-temperature-input');
		const languageSelect = document.getElementById('codex-language-select');
		
		const model = modelSelect?.value || defaultModel;
		const temperature = Number(temperatureInput?.value || defaultTemperature);
		const codex_language = languageSelect?.value || activeBook?.target_language;
		
		localStorage.setItem('parallel-leaves-codex-model', model);
		localStorage.setItem('parallel-leaves-codex-temperature', String(temperature));
		
		return {model, temperature, rebuild, codex_language};
	}
	
	async function saveCodex(bookId) {
		const content = document.getElementById('codex-textarea').value;
		try {
			await window.api.saveCodex(bookId, content);
			window.showAlertModal('Codex updated successfully!', 'Saved');
		} catch (error) {
			window.showAlertModal('Error saving codex: ' + error.message, 'Save Failed');
		}
	}
	
	async function resetCodex(bookId) {
		// MODIFIED: Substituted standard window confirm with showConfirmationModal
		const choice = await window.showConfirmationModal(
			'Are you sure you want to reset the codex for this book? All content will be deleted.',
			'Reset Codex'
		);
		if (choice !== 'confirm') {
			return;
		}
		
		try {
			await window.api.resetCodex(bookId);
			if (urlBookId || activeBook?.id == bookId) {
				await editCodex(bookId);
			} else {
				await loadList();
			}
		} catch (error) {
			window.showAlertModal('Error resetting codex: ' + error.message, 'Reset Failed');
		}
	}
	
	async function rebuildCodex(bookId) {
		if (isGenerating) return;
		
		const processed = Number(activeBook?.codex_chunks_processed || 0);
		const total = Number(activeBook?.codex_chunks_total || 0);
		const isPartial = processed > 0 && processed < total;
		
		let rebuild = true;
		
		// NEW: Ask to resume or rebuild if codex is partially built
		if (isPartial) {
			const choice = await window.showConfirmationModal(
				'A partially generated codex was found. Do you want to resume the existing generation or start fresh?',
				'Resume or Rebuild Codex',
				{
					confirmText: 'Start Fresh (Rebuild)',
					cancelText: 'Cancel',
					showExtra: true,
					extraText: 'Resume Generation',
					extraClass: 'btn btn-primary flex-1'
				}
			);
			
			if (choice === 'extra') {
				rebuild = false; // Resume
			} else if (choice === 'confirm') {
				rebuild = true; // Start fresh / Rebuild
			} else {
				return; // Cancel
			}
		} else {
			const choice = await window.showConfirmationModal(
				'Rebuild this codex from the source manuscript? Existing codex content will be replaced.',
				'Rebuild Codex'
			);
			if (choice !== 'confirm') {
				return;
			}
			rebuild = true;
		}
		
		isGenerating = true;
		const statusEl = document.getElementById('codex-generation-status');
		const rebuildBtn = document.getElementById('codex-rebuild-btn');
		const saveBtn = document.getElementById('codex-save-btn');
		const textarea = document.getElementById('codex-textarea');
		const progressContainer = document.getElementById('codex-progress-container');
		const progressBar = document.getElementById('codex-progress-bar');
		const progressPercent = document.getElementById('codex-progress-percent');
		
		if (rebuildBtn) rebuildBtn.disabled = true;
		if (saveBtn) saveBtn.disabled = true;
		if (textarea) textarea.readOnly = true; // Make editor read-only during compilation
		if (progressContainer) progressContainer.classList.remove('hidden');
		
		try {
			const options = getGenerationOptions(rebuild);
			let start = await window.api.startCodex(bookId, options);
			if (start.status === 'complete') {
				if (statusEl) statusEl.textContent = 'Complete';
				if (progressBar) progressBar.value = 100;
				if (progressPercent) progressPercent.textContent = '100%';
				await editCodex(bookId);
				return;
			}
			
			while (true) {
				const status = await window.api.processCodexBatch(bookId, options);
				
				// MODIFIED: Realtime progress bar updating logic
				const currentPercent = status.total > 0 ? Math.round((status.processed / status.total) * 100) : 0;
				if (progressBar) progressBar.value = currentPercent;
				if (progressPercent) progressPercent.textContent = `${currentPercent}%`;
				
				// MODIFIED: Live textarea stream updates during building process
				if (status.codex_content && textarea) {
					textarea.value = status.codex_content;
				}
				
				if (status.status === 'complete') {
					if (statusEl) statusEl.textContent = 'Complete';
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
			window.showAlertModal('Error rebuilding codex: ' + error.message, 'Generation Failed');
		} finally {
			isGenerating = false;
			if (rebuildBtn) rebuildBtn.disabled = false;
			if (saveBtn) saveBtn.disabled = false;
			if (textarea) textarea.readOnly = false;
			if (progressContainer) progressContainer.classList.add('hidden');
		}
	}
	
	if (urlBookId) {
		editCodex(urlBookId);
	} else {
		loadList();
	}
});
