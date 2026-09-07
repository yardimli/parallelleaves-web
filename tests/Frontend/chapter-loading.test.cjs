const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/src/js/book-editor/chapter-main.js'), 'utf8');
const loading = source.slice(source.indexOf('function finishPageLoading()'), source.indexOf('// NEW SECTION START: Non-disruptive'));

for (const savedPosition of [true, false]) {
    test(`loading waits for fonts and position restoration (saved=${savedPosition})`, async () => {
        const events = [];
        let fontsReady;
        const fonts = new Promise(resolve => { fontsReady = resolve; });
        const scope = {
            viewInitialized: false, viewInitializing: false,
            chapterEditorViews: new Map(),
            document: {
                readyState: 'complete', fonts: {ready: fonts},
                getElementById: id => id === 'chapter-loading-overlay'
                    ? {remove: () => events.push('hidden')} : {},
                body: {setAttribute: () => {}},
            },
            requestAnimationFrame: callback => setImmediate(callback),
            restoreScrollPositions: () => { events.push('restored'); return savedPosition; },
            scrollToChapter: (id, callback, behavior) => {
                assert.equal(behavior, 'instant');
                events.push('chapter');
            },
            setActiveChapterId: () => {},
            window: {showAlert: message => assert.fail(message)},
            console, t: key => key,
        };
        vm.runInNewContext(loading, scope);
        const done = scope.initializeView(10, {chapters: [{id: 20}]}, null);
        await new Promise(resolve => setImmediate(resolve));
        assert.deepEqual(events, []);
        fontsReady();
        await done;
        assert.deepEqual(events, savedPosition ? ['restored', 'hidden'] : ['restored', 'chapter', 'hidden']);
        assert.equal(scope.viewInitialized, true);
    });
}
