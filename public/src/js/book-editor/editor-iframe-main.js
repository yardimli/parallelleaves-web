// Compatibility entry point for the standalone editor route.
import {createChapterEditor} from './chapter-editor.js';
const mount = document.getElementById('editor-container');
mount.style.position = 'relative';
const editor = createChapterEditor(document.getElementById('editor-container'), (type, payload) => {
	window.parent.postMessage({type, payload}, window.location.origin);
});
window.addEventListener('message', event => {
	if (event.source !== window.parent || event.origin !== window.location.origin) return;
	if (event.data.type === 'init') {
		document.documentElement.setAttribute('data-theme', event.data.payload.theme);
		document.documentElement.classList.toggle('dark', event.data.payload.theme === 'dark');
	}
	editor.dispatch(event.data);
});
const resizeObserver = new ResizeObserver(() => {
	window.parent.postMessage({type: 'resize', payload: {height: document.body.scrollHeight + 75}}, window.location.origin);
});
resizeObserver.observe(mount);
