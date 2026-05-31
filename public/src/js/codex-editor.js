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
			container.innerHTML = `
				<div class="mb-4 flex items-center justify-between gap-3">
					<button id="codex-back-btn" class="btn btn-sm btn-outline">← ${backActionLabel}</button>
					<span id="codex-generation-status" class="text-sm text-base-content/70">${escapeHtml(statusLabel(book))}</span>
				</div>
				<h2 class="text-2xl font-semibold mb-4">Editing Codex for: <span class="italic">${escapeHtml(book.title)}</span></h2>
				<div class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-3 mb-4 items-end">
					<label class="form-control">
						<span class="label-text">Model</span>
						<select id="codex-model-select" class="select select-bordered">
							${modelOptionsHtml(defaultModel)}
						</select>
					</label>
					<label class="form-control w-40">
						<span class="label-text">Temperature</span>
						<input id="codex-temperature-input" type="number" min="0" max="2" step="0.1" class="input input-bordered" value="${escapeHtml(defaultTemperature)}">
					</label>
					<button id="codex-rebuild-btn" class="btn btn-warning">Rebuild Codex</button>
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
		const model = modelSelect?.value || defaultModel;
		const temperature = Number(temperatureInput?.value || defaultTemperature);
		localStorage.setItem('parallel-leaves-codex-model', model);
		localStorage.setItem('parallel-leaves-codex-temperature', String(temperature));
		return {model, temperature, rebuild};
	}
	
	async function saveCodex(bookId) {
		const content = document.getElementById('codex-textarea').value;
		try {
			await window.api.saveCodex(bookId, content);
			window.showAlert ? window.showAlert('Codex updated successfully!') : alert('Codex updated successfully!');
			await editCodex(bookId);
		} catch (error) {
			window.showAlert ? window.showAlert('Error saving codex: ' + error.message) : alert('Error saving codex: ' + error.message);
		}
	}
	
	async function resetCodex(bookId) {
		if (!confirm('Are you sure you want to reset the codex for this book? All content will be deleted.')) return;
		try {
			await window.api.resetCodex(bookId);
			if (urlBookId || activeBook?.id == bookId) {
				await editCodex(bookId);
			} else {
				await loadList();
			}
		} catch (error) {
			alert('Error resetting codex: ' + error.message);
		}
	}
	
	async function rebuildCodex(bookId) {
		if (isGenerating) return;
		if (!confirm('Rebuild this codex from the source manuscript? Existing codex content will be replaced.')) return;
		isGenerating = true;
		const statusEl = document.getElementById('codex-generation-status');
		const rebuildBtn = document.getElementById('codex-rebuild-btn');
		if (rebuildBtn) rebuildBtn.disabled = true;
		
		try {
			const options = getGenerationOptions(true);
			let start = await window.api.startCodex(bookId, options);
			if (start.status === 'complete') {
				if (statusEl) statusEl.textContent = 'Complete';
				await editCodex(bookId);
				return;
			}
			
			while (true) {
				const status = await window.api.processCodexBatch(bookId, options);
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
			alert('Error rebuilding codex: ' + error.message);
		} finally {
			isGenerating = false;
			if (rebuildBtn) rebuildBtn.disabled = false;
		}
	}
	
	if (urlBookId) {
		editCodex(urlBookId);
	} else {
		loadList();
	}
});
