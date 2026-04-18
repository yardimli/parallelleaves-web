document.addEventListener('DOMContentLoaded', async () => {
	const container = document.getElementById('codex-container');
	// MODIFIED: Parse URL parameters to check for direct book loading
	const urlParams = new URLSearchParams(window.location.search);
	const urlBookId = urlParams.get('bookId');
	
	async function loadList() {
		container.innerHTML = '<p>Loading books...</p>';
		try {
			const books = await window.api.getCodexBooks();
			if (!books || books.length === 0) {
				container.innerHTML = '<p>You have not synced any books yet.</p>';
				return;
			}
			
			let html = `
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
			`;
			
			books.forEach(book => {
				html += `
					<tr>
						<td>
							<div class="font-bold">${book.title}</div>
							<div class="text-sm opacity-50">${book.author || 'Unknown Author'}</div>
						</td>
						<td>
							<span class="badge badge-ghost">${book.source_language}</span> →
							<span class="badge badge-ghost">${book.target_language}</span>
						</td>
						<td><span class="badge badge-info">${book.codex_status}</span></td>
						<td>
							<button class="btn btn-sm btn-primary" onclick="window.editCodex(${book.id})">Edit Codex</button>
							<button class="btn btn-sm btn-outline btn-warning" onclick="window.resetCodex(${book.id})" ${book.codex_status === 'none' ? 'disabled' : ''}>Reset</button>
						</td>
					</tr>
				`;
			});
			
			html += '</tbody></table>';
			container.innerHTML = html;
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error: ${error.message}</p>`;
		}
	}
	
	window.editCodex = async (bookId) => {
		container.innerHTML = '<p>Loading details...</p>';
		try {
			const book = await window.api.getCodexDetails(bookId);
			if (!book) throw new Error("Book not found.");
			
			// MODIFIED: Determine back action based on how the page was loaded
			const backAction = urlBookId ? "window.location.href='index.html'" : "window.location.reload()";
			
			let html = `
				<div class="mb-4"><button class="btn btn-sm btn-outline" onclick="${backAction}">&larr; Back</button></div>
				<h2 class="text-2xl font-semibold mb-4">Editing Codex for: <span class="italic">${book.title}</span></h2>
				<div class="form-control">
					<label class="label"><span class="label-text">Codex HTML Content</span></label>
					<textarea id="codex-textarea" class="textarea textarea-bordered w-full h-96 font-mono">${book.codex_content || ''}</textarea>
				</div>
				<div class="form-control mt-6">
					<button class="btn btn-success" onclick="window.saveCodex(${book.id})">Save Codex</button>
				</div>
			`;
			container.innerHTML = html;
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error: ${error.message}</p>`;
		}
	};
	
	window.saveCodex = async (bookId) => {
		const content = document.getElementById('codex-textarea').value;
		try {
			await window.api.saveCodex(bookId, content);
			alert('Codex updated successfully!');
		} catch (error) {
			alert('Error saving codex: ' + error.message);
		}
	};
	
	window.resetCodex = async (bookId) => {
		if (confirm('Are you sure you want to reset the codex for this book? All content will be deleted and regenerated.')) {
			try {
				await window.api.resetCodex(bookId);
				loadList();
			} catch (error) {
				alert('Error resetting codex: ' + error.message);
			}
		}
	};
	
	// MODIFIED: Load specific book if ID is present, otherwise load the list
	if (urlBookId) {
		window.editCodex(urlBookId);
	} else {
		loadList();
	}
});
