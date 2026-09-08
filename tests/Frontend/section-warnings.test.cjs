const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/src/js/book-editor/chapter-main.js'), 'utf8');
const scope = {
    htmlToPlainText: html => html.replace(/<[^>]*>/g, ''),
    t: (key, {count}) => `${key}:${count}`,
};
vm.runInNewContext(
    source.slice(source.indexOf('function isTargetSectionEmpty'), source.indexOf('function synchronizeMarkers')) +
    source.slice(source.indexOf('function getSectionWarnings'), source.indexOf('async function renderManuscript')),
    scope
);

for (const [name, targets, count] of [
    ['trailing empty chapters', ['translated', '', '<p> </p>', '[[#2]]{{#2}}'], 0],
    ['entirely untranslated book', ['', '', ''], 0],
    ['empty book', [], 0],
    ['middle gap followed by unfinished chapters', ['translated', '', 'translated', '', ''], 1],
    ['multiple middle gaps', ['translated', '', '', 'translated'], 2],
    ['missing opening translation', ['', 'translated', ''], 1],
]) {
    test(name, () => {
        const warnings = scope.getSectionWarnings(targets.map(target_content => ({target_content})), new Set());
        assert.equal(warnings.length, count ? 1 : 0);
        if (count) assert.equal(warnings[0], `editor.sectionWarnings.emptyTargets:${count}`);
    });
}

test('marker alignment warnings remain visible with trailing empty chapters', () => {
    const warnings = scope.getSectionWarnings([{target_content: ''}], new Set([1]));
    assert.equal(warnings.length, 1);
    assert.equal(warnings[0], 'editor.sectionWarnings.misaligned:1');
});
