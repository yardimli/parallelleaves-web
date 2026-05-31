/**
 * Centralized alert and confirmation modal controls using DaisyUI/HTML5 dialog tags.
 */

// NEW: Expose showAlertModal globally
window.showAlertModal = function (message, title = 'Information') {
	const modal = document.getElementById('alert-modal');
	if (modal) {
		const titleEl = modal.querySelector('#alert-modal-title');
		const contentEl = modal.querySelector('#alert-modal-content');
		if (titleEl) {
			titleEl.textContent = title;
		}
		if (contentEl) {
			contentEl.textContent = message;
		}
		
		if (typeof modal.showModal === 'function') {
			if (!modal.open) {
				modal.showModal();
			}
		} else {
			alert(message);
		}
	} else {
		alert(title + ': ' + message);
	}
};

// NEW: Expose showConfirmationModal globally to handle 2-way and 3-way interactions elegantly
window.showConfirmationModal = function (message, title = 'Confirm', options = {}) {
	return new Promise((resolve) => {
		const modal = document.getElementById('confirmation-modal');
		if (!modal) {
			const res = confirm(message);
			resolve(res ? 'confirm' : 'cancel');
			return;
		}
		
		const titleEl = modal.querySelector('#confirmation-modal-title');
		const contentEl = modal.querySelector('#confirmation-modal-content');
		const confirmBtn = modal.querySelector('#confirmation-modal-confirm-btn');
		const cancelBtn = modal.querySelector('#confirmation-modal-cancel-btn');
		const declineBtn = modal.querySelector('#confirmation-modal-decline-btn');
		
		if (titleEl) {
			titleEl.textContent = title;
		}
		if (contentEl) {
			contentEl.textContent = message;
		}
		
		if (confirmBtn) {
			confirmBtn.textContent = options.confirmText || 'Confirm';
			confirmBtn.className = options.confirmClass || 'btn btn-error flex-1';
		}
		if (cancelBtn) {
			cancelBtn.textContent = options.cancelText || 'Cancel';
			cancelBtn.className = options.cancelClass || 'btn flex-1';
		}
		
		// MODIFIED: Support third action button if options.showExtra is true (e.g. for Resume vs Rebuild)
		if (options.showExtra && declineBtn) {
			declineBtn.textContent = options.extraText || 'Resume';
			declineBtn.className = options.extraClass || 'btn btn-primary flex-1';
			declineBtn.classList.remove('hidden');
		} else {
			if (declineBtn) {
				declineBtn.classList.add('hidden');
			}
		}
		
		const handleConfirm = (e) => {
			e.preventDefault();
			modal.close();
			cleanup();
			resolve('confirm');
		};
		
		const handleCancel = (e) => {
			e.preventDefault();
			modal.close();
			cleanup();
			resolve('cancel');
		};
		
		const handleExtra = (e) => {
			e.preventDefault();
			modal.close();
			cleanup();
			resolve('extra');
		};
		
		const handleClose = () => {
			cleanup();
			resolve('cancel');
		};
		
		const cleanup = () => {
			if (confirmBtn) {
				confirmBtn.removeEventListener('click', handleConfirm);
			}
			if (cancelBtn) {
				cancelBtn.removeEventListener('click', handleCancel);
			}
			if (declineBtn) {
				declineBtn.removeEventListener('click', handleExtra);
			}
			modal.removeEventListener('close', handleClose);
		};
		
		if (confirmBtn) {
			confirmBtn.addEventListener('click', handleConfirm);
		}
		if (cancelBtn) {
			cancelBtn.addEventListener('click', handleCancel);
		}
		if (declineBtn) {
			declineBtn.addEventListener('click', handleExtra);
		}
		modal.addEventListener('close', handleClose);
		
		if (typeof modal.showModal === 'function') {
			if (!modal.open) {
				modal.showModal();
			}
		} else {
			const res = confirm(message);
			resolve(res ? 'confirm' : 'cancel');
		}
	});
};
