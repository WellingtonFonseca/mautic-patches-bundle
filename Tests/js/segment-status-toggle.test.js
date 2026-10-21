// The logic of the segment status toggle (Resources/js/segment-status-toggle.js): what the answer of the server says, and what the
// label and the menu item show for it. The DOM part is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/segment-status-toggle.js');

const cfg = {active: 'Ativo', inactive: 'Inativo', activate: 'Ativar', deactivate: 'Desativar'};

test('statusOf: the data-status of the icon in the answer', () => {
    assert.strictEqual(api.statusOf({statusHtml: '<i class="ri-toggle-fill" data-status="published"></i>'}), 'published');
    assert.strictEqual(api.statusOf({statusHtml: '<i class="ri-toggle-line" data-status="unpublished"></i>'}), 'unpublished');
});

test('statusOf: expired, pending, no icon or no answer are "not active"', () => {
    assert.strictEqual(api.statusOf({statusHtml: '<i data-status="expired"></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({statusHtml: '<i data-status="pending"></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({statusHtml: '<i></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({}), 'unpublished');
    assert.strictEqual(api.statusOf(null), 'unpublished');
});

test('view: an active segment is a green label and the menu offers to deactivate it', () => {
    assert.deepStrictEqual(api.view('published', cfg), {
        labelClass: 'label-success', labelText: 'Ativo', action: 'deactivate', actionText: 'Desativar', actionIcon: 'ri-close-circle-line',
    });
});

test('view: an inactive segment is a red label and the menu offers to activate it', () => {
    assert.deepStrictEqual(api.view('unpublished', cfg), {
        labelClass: 'label-danger', labelText: 'Inativo', action: 'activate', actionText: 'Ativar', actionIcon: 'ri-checkbox-circle-line',
    });
});

test('place: a label that is not first in its block is moved to the start, once, and marked', () => {
    const insertions = [];
    const block = {firstChild: {name: 'link'}, insertBefore(node, ref) { insertions.push([node.name, ref.name]); this.firstChild = node; }};
    const label = {name: 'label', parentNode: block, attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }};
    const doc = {querySelectorAll: (sel) => (sel === '.segment-status-label:not([data-placed])' && !label.attrs['data-placed'] ? [label] : [])};

    assert.strictEqual(api.place(doc), 1);
    assert.deepStrictEqual(insertions, [['label', 'link']]);
    assert.strictEqual(label.attrs['data-placed'], '1');
    // a second pass finds nothing to do
    assert.strictEqual(api.place(doc), 0);
    assert.strictEqual(insertions.length, 1);
});

test('place: a label that already starts its block is only marked', () => {
    const label = {name: 'label', attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }};
    label.parentNode = {firstChild: label, insertBefore() { throw new Error('moved'); }};
    const doc = {querySelectorAll: () => [label]};

    assert.strictEqual(api.place(doc), 0);
    assert.strictEqual(label.attrs['data-placed'], '1');
});

test('measure: a hidden label with the text of Inativo is measured and its width goes to --segment-status-width', () => {
    const props = {};
    const appended = [];
    const doc = {
        body: {appendChild: (n) => appended.push(n), removeChild: (n) => appended.splice(appended.indexOf(n), 1)},
        documentElement: {style: {setProperty: (k, v) => { props[k] = v; }}},
        createElement: () => ({attrs: {}, style: {}, setAttribute(k, v) { this.attrs[k] = v; }, getBoundingClientRect: () => ({width: 49.5})}),
    };

    assert.strictEqual(api.measure(doc, cfg), 49.5);
    assert.deepStrictEqual(props, {'--segment-status-width': '49.5px'});
    assert.strictEqual(appended.length, 0, 'the probe is removed');
});

test('measure: nothing is set when the width cannot be measured (a hidden page)', () => {
    const props = {};
    const doc = {
        body: {appendChild() {}, removeChild() {}},
        documentElement: {style: {setProperty: (k, v) => { props[k] = v; }}},
        createElement: () => ({attrs: {}, style: {}, setAttribute() {}, getBoundingClientRect: () => ({width: 0})}),
    };

    assert.strictEqual(api.measure(doc, cfg), 0);
    assert.deepStrictEqual(props, {});
});

test('place: a label printed inside the name link (the campaign list) goes to the start of the block, outside the link', () => {
    const insertions = [];
    const block = {firstChild: {name: 'link'}, insertBefore(node, ref) { insertions.push([node.name, ref.name]); this.firstChild = node; }};
    const link = {tagName: 'A', parentNode: block};
    const label = {name: 'label', parentNode: link, attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }};
    const doc = {querySelectorAll: () => (label.attrs['data-placed'] ? [] : [label])};

    assert.strictEqual(api.place(doc), 1);
    assert.deepStrictEqual(insertions, [['label', 'link']]);
});
