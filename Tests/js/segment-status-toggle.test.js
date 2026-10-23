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
        labelClass: 'patches-badge--production', labelText: 'Ativo', action: 'deactivate', actionText: 'Desativar', actionIcon: 'ri-close-circle-line',
    });
});

test('view: an inactive segment is a red label and the menu offers to activate it', () => {
    assert.deepStrictEqual(api.view('unpublished', cfg), {
        labelClass: 'patches-badge--failed', labelText: 'Inativo', action: 'activate', actionText: 'Ativar', actionIcon: 'ri-checkbox-circle-line',
    });
});

test('place: a label that is not first in its block is moved to the start, once, and marked', () => {
    const insertions = [];
    const block = {firstChild: {name: 'link'}, insertBefore(node, ref) { insertions.push([node.name, ref.name]); this.firstChild = node; }};
    const label = {name: 'label', parentNode: block, attrs: {}, getAttribute(k) { return this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = v; }};
    const doc = {querySelectorAll: (sel) => (sel === '.segment-status-label:not([data-placed])' && !label.attrs['data-placed'] ? [label] : [])};

    assert.strictEqual(api.place(doc), 1);
    assert.deepStrictEqual(insertions, [['label', 'link']]);
    assert.strictEqual(label.attrs['data-placed'], '1');
    // a second pass finds nothing to do
    assert.strictEqual(api.place(doc), 0);
    assert.strictEqual(insertions.length, 1);
});

test('place: a label that already starts its block is only marked', () => {
    const label = {name: 'label', attrs: {}, getAttribute(k) { return this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = v; }};
    label.parentNode = {firstChild: label, insertBefore() { throw new Error('moved'); }};
    const doc = {querySelectorAll: () => [label]};

    assert.strictEqual(api.place(doc), 0);
    assert.strictEqual(label.attrs['data-placed'], '1');
});

test('place: a label printed inside the name link (the campaign list) goes to the start of the block, outside the link', () => {
    const insertions = [];
    const block = {firstChild: {name: 'link'}, insertBefore(node, ref) { insertions.push([node.name, ref.name]); this.firstChild = node; }};
    const link = {tagName: 'A', parentNode: block};
    const label = {name: 'label', parentNode: link, attrs: {}, getAttribute(k) { return this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = v; }};
    const doc = {querySelectorAll: () => (label.attrs['data-placed'] ? [] : [label])};

    assert.strictEqual(api.place(doc), 1);
    assert.deepStrictEqual(insertions, [['label', 'link']]);
});

test('withoutAlias: "(alias)" and the spaces before it go, whatever is around the text stays', () => {
    assert.strictEqual(api.withoutAlias('Compras (compras)', 'compras'), 'Compras');
    assert.strictEqual(api.withoutAlias('\n   Campo texto: nome1 = Disciplina 01 (cftext)\n  ', 'cftext'), '\n   Campo texto: nome1 = Disciplina 01\n  ');
    assert.strictEqual(api.withoutAlias('Nome (alias)', ''), 'Nome (alias)');
    assert.strictEqual(api.withoutAlias('Nome', 'alias'), 'Nome');
});

test('withoutAlias: only the exact alias; a name with its own parentheses keeps them, and the last match is the alias', () => {
    assert.strictEqual(api.withoutAlias('Vendas (SP) (cfvendas)', 'cfvendas'), 'Vendas (SP)');
    assert.strictEqual(api.withoutAlias('Vendas (SP)', 'cfvendas'), 'Vendas (SP)');
    assert.strictEqual(api.withoutAlias('Vendas (cfvendas) (cfvendas)', 'cfvendas'), 'Vendas (cfvendas)');
});

test('place: the alias is taken off the text of the block when the label carries it', () => {
    const text = {nodeType: 3, nodeValue: 'Compras (compras)', nextSibling: null};
    const link = {nodeType: 1, firstChild: text, nextSibling: null};
    const block = {firstChild: link, insertBefore() {}};
    const label = {name: 'label', parentNode: block, attrs: {'data-alias': 'compras'}, getAttribute(k) { return this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = v; }};
    block.firstChild = label; // already first: nothing to move
    label.nextSibling = link;
    const doc = {querySelectorAll: () => [label]};

    api.place(doc);

    assert.strictEqual(text.nodeValue, 'Compras');
    assert.strictEqual(label.attrs['data-placed'], '1');
});

test('colorCounts: a count with contacts is blue, an empty one is gray, and it follows a text changed later', () => {
    const badge = (text) => {
        const classes = new Set(['label', 'label-gray', 'col-count']);
        return {textContent: text, removeAttribute: () => {}, classList: {add: (...n) => n.forEach((x) => classes.add(x)), remove: (...n) => n.forEach((x) => classes.delete(x)), toggle: (name, on) => { if (on) { classes.add(name); } else { classes.delete(name); } }}, classes};
    };
    const full = badge('Ver 6 Contatos'), empty = badge('Ver 0 Contatos'), none = badge('Sem contatos');
    const doc = {querySelectorAll: () => [full, empty, none]};

    api.colorCounts(doc);
    assert.ok(full.classes.has('patches-badge--info') && !full.classes.has('patches-badge--neutral'));
    assert.ok(empty.classes.has('patches-badge--neutral') && !empty.classes.has('patches-badge--info'));
    assert.ok(none.classes.has('patches-badge--neutral') && !none.classes.has('patches-badge--info'), 'a text without a number is an empty segment');

    empty.textContent = 'Ver 3 Contatos';
    none.classes.add('patches-badge--info');
    none.textContent = 'Sem contatos';
    api.colorCounts(doc);
    assert.ok(empty.classes.has('patches-badge--info') && !empty.classes.has('patches-badge--neutral'));
    assert.ok(none.classes.has('patches-badge--neutral') && !none.classes.has('patches-badge--info'), 'a blue badge goes back to gray when the segment is emptied');
});
