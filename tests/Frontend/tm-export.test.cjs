const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const scope = {};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/src/js/translation-memory/tm-export.js'), 'utf8')
    .replace('export function', 'function') + '\nthis.build = buildMemoryXml;', scope);

test('XML round-trip preserves all prompt pairs, edited targets, and special characters', () => {
    const entries = Array.from({length: 25}, (_, id) => ({
        source_sentence: `Source ${id} & <text> ]]>\nSecond line`,
        original_target_sentence: 'Do not export this original target',
        edited_target_sentence: `Çeviri ${id}`,
    }));
    const xml = scope.build(entries, {source_language: 'English (US)', target_language: 'Turkish'});
    const parsed = spawnSync('python', ['-c',
        'import sys,json,xml.etree.ElementTree as E; print(json.dumps(E.fromstring(sys.stdin.buffer.read()).text))'],
        {input: xml, encoding: 'utf8'});
    assert.equal(parsed.status, 0, parsed.stderr);
    const prompt = JSON.parse(parsed.stdout);
    const expected = entries.map(entry =>
        `<English (US)>${entry.source_sentence}</English (US)>\n<Turkish>${entry.edited_target_sentence}</Turkish>`
    ).join('\n');
    assert.equal(prompt, expected);
    assert.doesNotMatch(prompt, /Do not export/);
});

test('empty memory exports a valid empty document', () => {
    assert.match(scope.build([], {}), /<translation_memory><!\[CDATA\[\]\]><\/translation_memory>/);
});
