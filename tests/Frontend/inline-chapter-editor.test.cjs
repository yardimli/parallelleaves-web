const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const {buildSync} = require('esbuild');
let chromium;
try { ({chromium} = require('playwright')); } catch {}

test('inline chapter editors isolate editing, search, language and typography', {skip: !chromium}, async () => {
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const page = await browser.newPage();
        await page.setContent('<html lang="en"><body><div id="first" style="position:relative;margin-top:200px"></div><div id="second"></div></body></html>');
        const bundle = buildSync({
            entryPoints: [path.join(__dirname, '../../public/src/js/book-editor/chapter-editor.js')],
            bundle: true, write: false, format: 'iife', globalName: 'ChapterEditor', platform: 'browser',
        }).outputFiles[0].text;
        await page.addScriptTag({content: bundle});
        const result = await page.evaluate(() => {
            const events = [];
            const first = ChapterEditor.createChapterEditor(document.getElementById('first'), (type, payload) => events.push({type, payload}));
            const second = ChapterEditor.createChapterEditor(document.getElementById('second'), () => {});
            for (const [editor, chapterId, initialHtml] of [[first, 1, '<p>Alpha chapter</p>'], [second, 2, '<p>Beta chapter</p>']]) {
                editor.dispatch({type: 'init', payload: {initialHtml, chapterId, field: 'target_content', isEditable: true}});
            }
            first.view.dispatch(first.view.state.tr.insertText(' edited', 14));
            const saved = events.find(event => event.type === 'contentChanged').payload;
            first.dispatch({type: 'command', payload: {command: 'undo'}});
            const afterUndo = first.view.state.doc.textContent;
            first.dispatch({type: 'setSelection', payload: {from: 1, to: 6}});
            first.dispatch({type: 'command', payload: {command: 'bold'}});
            first.dispatch({type: 'prepareForGetFullHtml'});
            const html = events.findLast(event => event.type === 'fullHtmlResponse').payload.html;
            first.dispatch({type: 'search:findAndHighlight', payload: {query: 'Alpha'}});
            const matches = events.findLast(event => event.type === 'search:results').payload;
            first.dispatch({type: 'setSpellcheckLanguage', payload: {lang: 'fr'}});
            first.dispatch({type: 'updateTypography', payload: {styleProps: {'--editor-font-size': '22px'}}});
            first.dispatch({type: 'findAndScrollToText', payload: {text: 'Alpha'}});
            const scroll = events.findLast(event => event.type === 'scrollToCoordinates').payload.top;
            const expectedScroll = first.view.coordsAtPos(1).top - document.getElementById('first').getBoundingClientRect().top;
            const result = {
                saved, afterUndo, html, matches, scroll, expectedScroll,
                secondText: second.view.state.doc.textContent,
                secondHighlights: second.view.dom.querySelectorAll('.search-highlight').length,
                language: first.view.dom.lang, pageLanguage: document.documentElement.lang,
                firstSize: document.getElementById('first').style.getPropertyValue('--editor-font-size'),
                secondSize: document.getElementById('second').style.getPropertyValue('--editor-font-size'),
                iframeCount: document.querySelectorAll('iframe').length,
            };
            first.destroy(); second.destroy();
            result.remainingEditors = document.querySelectorAll('.ProseMirror').length;
            return result;
        });
        assert.equal(result.saved.chapterId, 1);
        assert.match(result.saved.value, /edited/);
        assert.equal(result.afterUndo, 'Alpha chapter');
        assert.match(result.html, /<strong>Alpha<\/strong>/);
        assert.deepEqual(result.matches, {chapterId: 1, matchCount: 1});
        assert.equal(result.secondText, 'Beta chapter');
        assert.equal(result.secondHighlights, 0);
        assert.equal(result.language, 'fr');
        assert.equal(result.pageLanguage, 'en');
        assert.equal(result.firstSize, '22px');
        assert.equal(result.secondSize, '');
        assert.equal(result.scroll, result.expectedScroll);
        assert.equal(result.iframeCount, 0);
        assert.equal(result.remainingEditors, 0);
    } finally {
        await browser.close();
    }
});
