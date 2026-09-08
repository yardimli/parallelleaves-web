document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-menu-toggle]');
    const nav = document.getElementById('site-nav');
    menu?.addEventListener('click', () => {
        const open = menu.getAttribute('aria-expanded') !== 'true';
        menu.setAttribute('aria-expanded', String(open));
        menu.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        nav.classList.toggle('is-open', open);
    });
    nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        nav.classList.remove('is-open'); menu?.setAttribute('aria-expanded', 'false');
    }));
    document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'paper' : 'dark';
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem('theme', theme); } catch (error) {}
    });
    document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(visible));
    }));
    const dialog = document.getElementById('screenshot-dialog');
    document.querySelectorAll('[data-screenshot]').forEach(link => link.addEventListener('click', event => {
        if (!dialog?.showModal) return;
        event.preventDefault();
        const img = dialog.querySelector('img');
        img.src = link.href; img.alt = link.querySelector('img').alt;
        dialog.querySelector('p').textContent = link.dataset.caption;
        dialog.showModal();
    }));
    document.querySelector('[data-close-screenshot]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });
    document.querySelectorAll('form[data-auth-action]').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        const message = form.querySelector('[role=alert]');
        const original = button.textContent;
        button.disabled = true; button.textContent = 'One moment…'; message.hidden = true;
        try {
            const response = await fetch(form.dataset.authAction, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body:JSON.stringify(Object.fromEntries(new FormData(form)))});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Please check your details and try again.');
            location.assign('/dashboard');
        } catch(error) {
            message.textContent = error.message || 'Unable to connect. Please try again.';
            message.hidden = false; message.focus();
            button.disabled = false; button.textContent = original;
        }
    }));
    document.querySelectorAll('form[data-submit-once]').forEach(form => form.addEventListener('submit', () => {
        const button = form.querySelector('[type=submit]'); button.disabled=true; button.textContent='One moment…';
    }));
});
