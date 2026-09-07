const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Load the browser module with only its DOM/i18n dependencies stubbed.
const source = fs.readFileSync(path.join(__dirname, '../../public/src/js/prompt-editors/translate-editor.js'), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace(/export const /g, 'const ');
function editor(api) {
    const scope = {
        window: {api},
        htmlToPlainText: text => text,
        t: (key, values) => key === 'prompt.translate.system.base'
            ? 'System\n##TRANSLATION_MEMORY##\n##STYLE_ANALYSIS_BLOCK##\n##CODEX_BLOCK##'
            : values.text,
    };
    vm.runInNewContext(source + '\nthis.build = buildPromptJson; this.expand = expandSystemPlaceholders;', scope);
    return scope;
}
const context = {
    bookId: 10, languageForPrompt: 'English', targetLanguage: 'Turkish',
    selectedText: 'Text to translate',
    translationPairs: [{source: 'A!er lunch, Georgia takes Isaiah...', target: 'Chapter translation'}],
};

test('expanded memory uses server results, while chapter blocks remain context messages', async () => {
    let requested;
    const scope = editor({
        getCodexDetails: async () => ({}),
        getTranslationMemoryForPrompt: async data => {
            requested = data;
            return '<English>Saved sentence</English>\n<Turkish>Edited sentence</Turkish>';
        },
    });
    const prompt = scope.build({}, context);
    const expanded = await scope.expand(prompt.system, context, prompt.user);
    assert.equal(requested.bookId, 10);
    assert.equal(requested.text, prompt.user);
    assert.match(expanded, /Saved sentence/);
    assert.doesNotMatch(expanded, /A!er lunch|Chapter translation|##TRANSLATION_MEMORY##/);
    assert.equal(prompt.context_pairs[0].content, context.translationPairs[0].source);
});

test('disabled memory is neither fetched nor displayed', async () => {
    const scope = editor({
        getCodexDetails: async () => ({}),
        getTranslationMemoryForPrompt: async () => { throw new Error('Unexpected memory lookup'); },
    });
    const prompt = scope.build({includeTranslationMemory: false}, context);
    const expanded = await scope.expand(prompt.system, context, prompt.user);
    assert.doesNotMatch(expanded, /TRANSLATION_MEMORY|A!er lunch/);
});

test('empty memory does not fall back to chapter blocks', async () => {
    const scope = editor({
        getCodexDetails: async () => ({}),
        getTranslationMemoryForPrompt: async () => '',
    });
    const prompt = scope.build({}, context);
    assert.equal(await scope.expand(prompt.system, context, prompt.user), 'System');
});
