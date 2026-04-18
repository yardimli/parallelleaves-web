document.addEventListener('DOMContentLoaded', async () => {
	const container = document.getElementById('tm-container');
	
	async function loadList() {
		container.innerHTML = '<p>Loading books...</p>';
		try {
			const books = await window.api.getTmBooks();
			if (!books || books.length === 0) {
				container.innerHTML = '<p>You have not synced any books with translation memories yet.</p>';
				return;
			}
			
			let html = `
				<table class="table w-full">
					<thead>
						<tr>
							<th>Title</th>
							<th>Languages</th>
							<th>TM Entries</th>
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
						<td>${book.tm_count}</td>
						<td>
							<button class="btn btn-sm btn-primary" onclick="window.viewTmDetails(${book.id}, '${book.title.replace(/'/g, "\\'")}')" ${book.tm_count == 0 ? 'disabled' : ''}>View Details</button>
							<button class="btn btn-sm btn-outline btn-error" onclick="window.deleteTm(${book.id})" ${book.tm_count == 0 ? 'disabled' : ''}>Delete</button>
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
	
	window.viewTmDetails = async (bookId, title) => {
		container.innerHTML = '<p>Loading details...</p>';
		try {
			const details = await window.api.getTmDetails(bookId);
			let html = `
				<div class="mb-4"><button class="btn btn-sm btn-outline" onclick="window.location.reload()">&larr; Back to Book List</button></div>
				<h2 class="text-2xl font-semibold mb-4">Translation Memory for: <span class="italic">${title}</span></h2>
			`;
			
			if (!details || details.length === 0) {
				html += '<p>No entries found.</p>';
			} else {
				html += `
					<table class="table w-full table-zebra">
						<thead><tr><th class="w-1/2">Source</th><th class="w-1/2">Target</th></tr></thead>
						<tbody>
				`;
				details.forEach(tm => {
					html += `<tr><td>${tm.source_sentence}</td><td>${tm.target_sentence}</td></tr>`;
				});
				html += '</tbody></table>';
			}
			container.innerHTML = html;
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error: ${error.message}</p>`;
		}
	};
	
	window.deleteTm = async (bookId) => {
		if (confirm('Are you sure you want to delete all TM entries for this book?')) {
			try {
				await window.api.deleteTm(bookId);
				loadList();
			} catch (error) {
				alert('Error deleting TM: ' + error.message);
			}
		}
	};
	
	loadList();
});
