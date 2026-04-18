document.addEventListener('DOMContentLoaded', async () => {
	const container = document.getElementById('logs-container');
	const pagination = document.getElementById('pagination-container');
	
	async function loadLogs(page = 1) {
		container.innerHTML = '<p>Loading logs...</p>';
		try {
			const data = await window.api.getLogs(page);
			
			if (!data.logs || data.logs.length === 0) {
				container.innerHTML = '<p>No API logs found.</p>';
				pagination.innerHTML = '';
				return;
			}
			
			let html = `
				<table class="table table-zebra w-full">
					<thead>
						<tr>
							<th>Timestamp</th>
							<th>Action</th>
							<th>Status</th>
							<th>Request</th>
							<th>Response</th>
						</tr>
					</thead>
					<tbody>
			`;
			
			data.logs.forEach(log => {
				const statusClass = log.response_code >= 400 ? 'badge-error' : 'badge-success';
				html += `
					<tr>
						<td>${new Date(log.created_at).toLocaleString()}</td>
						<td><span class="badge badge-neutral">${log.action}</span></td>
						<td><span class="badge ${statusClass}">${log.response_code}</span></td>
						<td><textarea readonly class="textarea textarea-bordered textarea-xs w-64 h-24 font-mono">${log.request_payload || ''}</textarea></td>
						<td><textarea readonly class="textarea textarea-bordered textarea-xs w-64 h-24 font-mono">${log.response_body || ''}</textarea></td>
					</tr>
				`;
			});
			
			html += '</tbody></table>';
			container.innerHTML = html;
			
			// Pagination
			pagination.innerHTML = '';
			for (let i = 1; i <= data.totalPages; i++) {
				const btn = document.createElement('button');
				btn.className = `join-item btn ${i === data.currentPage ? 'btn-active' : ''}`;
				btn.textContent = i;
				btn.onclick = () => loadLogs(i);
				pagination.appendChild(btn);
			}
		} catch (error) {
			container.innerHTML = `<p class="text-error">Error loading logs: ${error.message}</p>`;
		}
	}
	
	loadLogs(1);
});
