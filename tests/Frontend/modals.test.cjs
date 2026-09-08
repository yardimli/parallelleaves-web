const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/src/js/modals.js'), 'utf8');

function setup() {
    const form = new EventTarget();
    const input = {value: '', focus() {}, select() {}};
    const modal = new EventTarget();
    modal.querySelector = selector => selector.endsWith('-form') ? form : selector.endsWith('-input') ? input : {};
    modal.showModal = () => {};
    modal.close = () => modal.dispatchEvent(new Event('close'));
    const scope = {window: {}, document: {getElementById: () => modal}};
    vm.runInNewContext(source, scope);
    return {api: scope.window, form, input, modal};
}

test('rename returns saved text and supports reopening after cancel', async () => {
    const {api, form, input, modal} = setup();
    const cancelled = api.showInputModal('Rename', 'Title', 'Old title');
    assert.equal(input.value, 'Old title');
    modal.close();
    assert.equal(await cancelled, null);
    const saved = api.showInputModal('Rename', 'Title', 'Old title');
    input.value = ' New title ';
    form.dispatchEvent(new Event('submit', {cancelable: true}));
    assert.equal(await saved, 'New title');
});

test('confirmation fallback distinguishes confirmation from cancellation', async () => {
    const scope = {window: {}, document: {getElementById: () => null}, confirm: () => false};
    vm.runInNewContext(source, scope);
    assert.equal(await scope.window.showConfirmationModal('Delete?'), 'cancel');
    scope.confirm = () => true;
    assert.equal(await scope.window.showConfirmationModal('Delete?'), 'confirm');
});
