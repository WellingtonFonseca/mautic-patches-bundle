// The logic of the segment status toggle (Resources/js/list-status-toggle.js): what the answer of the server says, and what the
// label and the menu item show for it. The DOM part is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/list-status-toggle.js');

const cfg = {active: 'Ativo', inactive: 'Inativo', activate: 'Ativar', deactivate: 'Desativar'};
const ccfg = Object.assign({column: 'Status'}, cfg);

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
        labelClass: 'patches-badge--production', labelText: 'Ativo', labelIcon: 'ri-play-fill', action: 'deactivate', actionText: 'Desativar', actionIcon: 'ri-close-circle-line',
    });
});

test('view: an inactive segment is a red label and the menu offers to activate it', () => {
    assert.deepStrictEqual(api.view('unpublished', cfg), {
        labelClass: 'patches-badge--failed', labelText: 'Inativo', labelIcon: 'ri-stop-fill', action: 'activate', actionText: 'Ativar', actionIcon: 'ri-checkbox-circle-line',
    });
});

test('place: a label that is not first in its block is moved to the start, once, and marked', () => {
    const insertions = [];
    const block = {firstChild: {name: 'link'}, insertBefore(node, ref) { insertions.push([node.name, ref.name]); this.firstChild = node; }};
    const label = {name: 'label', parentNode: block, attrs: {}, getAttribute(k) { return this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = v; }};
    const doc = {querySelectorAll: (sel) => (sel === '.patches-status-label:not([data-placed])' && !label.attrs['data-placed'] ? [label] : [])};

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
        return {textContent: text, removeAttribute: () => {}, classList: {contains: (n) => classes.has(n), add: (...n) => n.forEach((x) => classes.add(x)), remove: (...n) => n.forEach((x) => classes.delete(x)), toggle: (name, on) => { if (on) { classes.add(name); } else { classes.delete(name); } }}, classes};
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

test('toggleUpdate: the Update item of the row is shown while the campaign is active and hidden otherwise, only the one of that campaign', () => {
    const link = () => ({attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }, removeAttribute(k) { delete this.attrs[k]; }});
    const mine = link(), other = link();
    const asked = [];
    const row = {querySelectorAll(selector) { asked.push(selector); return [mine]; }};

    api.toggleUpdate(row, 'campaign-rebuild', '7', 'unpublished');
    assert.deepStrictEqual(mine.attrs, {hidden: 'hidden'});
    api.toggleUpdate(row, 'campaign-rebuild', '7', 'published');
    assert.deepStrictEqual(mine.attrs, {});
    assert.deepStrictEqual(other.attrs, {});
    assert.deepStrictEqual(asked, ['a[href$="/s/campaign-rebuild/7"]', 'a[href$="/s/campaign-rebuild/7"]']);

    api.toggleUpdate(row, 'segment-rebuild', '9', 'published');
    assert.strictEqual(asked[2], 'a[href$="/s/segment-rebuild/9"]');
});

/** A fake DOM node with just what addCampaignStatusColumn uses; `children` is the list `insertBefore` works on. */
function node(name) {
    return {
        name, attrs: {}, children: [], className: '', style: {}, _text: '',
        get textContent() { return this._text; },
        set textContent(v) { this._text = v; this.children.forEach((c) => { c.parentNode = null; }); this.children = []; },
        setAttribute(k, v) { this.attrs[k] = v; },
        getAttribute(k) { return this.attrs[k]; },
        closest(selector) {
            const cls = selector.replace(/^td\./, '');
            for (let n = this; n; n = n.parentNode) { if (n.className && n.className.split(' ').includes(cls)) { return n; } }
            return null;
        },
        appendChild(child) {
            if (child.parentNode) { child.parentNode.children.splice(child.parentNode.children.indexOf(child), 1); } // a node is in one place only
            child.parentNode = this;
            this.children.push(child);
        },
        insertBefore(child, ref) {
            child.parentNode = this;
            this.children.splice(ref ? this.children.indexOf(ref) : this.children.length, 0, child);
        },
        get nextSibling() { const i = this.parentNode.children.indexOf(this); return this.parentNode.children[i + 1] || null; }
    };
}

/** A campaign table: header [menu, prefix, name] and rows [menu, prefix, name(label)], the label in the name cell. */
function campaignTable(rowCount, labelled = true, status = 'published') {
    const headRow = node('tr'), menuTh = node('th'), prefixTh = node('th'), nameTh = node('th');
    [menuTh, prefixTh, nameTh].forEach((th) => headRow.appendChild(th));
    const rows = [];
    for (let k = 0; k < rowCount; k++) {
        const row = node('tr'), cells = [node('td'), node('td'), node('td')];
        cells.forEach((c) => row.appendChild(c));
        if (labelled) {
            const label = node('label');
            label.attrs = {'data-status': status};
            cells[2].appendChild(label);
            row.label = label;
        }
        row.cells = cells;
        row.querySelector = (selector) => (selector === 'td' ? row.children[0] : (selector === '.patches-status-label' ? row.label || null : null));
        rows.push(row);
    }
    const table = {
        querySelector(selector) {
            if (selector === 'th.col-campaign-status') { return headRow.children.find((c) => c.className.includes('col-campaign-status')) || null; }
            if (selector === 'thead tr') { return {querySelector: () => headRow.children[0]}; }
            return null;
        },
        querySelectorAll: () => rows
    };
    const doc = {
        querySelector: (selector) => (selector === '#campaignTable' ? table : null),
        createElement: (tag) => { const n = node(tag); n.ownerDocument = doc; return n; }
    };
    rows.forEach((row) => { if (row.label) { row.label.ownerDocument = doc; } });

    return {doc, headRow, rows};
}

test('addCampaignStatusColumn: a Status header and a cell per row go right after the menu column, and the label moves into its cell', () => {
    const {doc, headRow, rows} = campaignTable(2);

    assert.strictEqual(api.addCampaignStatusColumn(doc, ccfg), true);

    assert.strictEqual(headRow.children.length, 4);
    const th = headRow.children[1];
    assert.match(th.className, /col-campaign-status/);
    assert.strictEqual(th.attrs.scope, 'col');
    assert.strictEqual(th.children[0].children[0].textContent, 'Status');
    assert.strictEqual(th.children[0].style.pointerEvents, 'none', 'the header is not a sort link');
    rows.forEach((row) => {
        assert.strictEqual(row.children.length, 4);
        const cell = row.children[1];
        assert.strictEqual(cell.className, 'col-campaign-status');
        assert.deepStrictEqual(cell.children, [row.label]);
        assert.strictEqual(row.label.attrs['data-placed'], '1');
        assert.strictEqual(row.cells[2].children.length, 0, 'the name cell no longer holds the label');
    });
});

test('addCampaignStatusColumn: done once per drawing of the table', () => {
    const {doc, headRow} = campaignTable(1);

    assert.strictEqual(api.addCampaignStatusColumn(doc, ccfg), true);
    assert.strictEqual(api.addCampaignStatusColumn(doc, ccfg), false);
    assert.strictEqual(headRow.children.length, 4);
});

test('addCampaignStatusColumn: a row without a label still gets an empty cell, so the columns line up', () => {
    const {doc, rows} = campaignTable(1, false);

    api.addCampaignStatusColumn(doc, ccfg);

    assert.strictEqual(rows[0].children.length, 4);
    assert.strictEqual(rows[0].children[1].children.length, 0);
});

test('addCampaignStatusColumn: no campaign table, or a table without a header, is left alone', () => {
    assert.strictEqual(api.addCampaignStatusColumn({querySelector: () => null}, ccfg), false);
    const noHead = {querySelector: (s) => (s === '#campaignTable' ? {querySelector: () => null} : null)};
    assert.strictEqual(api.addCampaignStatusColumn(noHead, ccfg), false);
});

test('addCampaignStatusColumn: a row without any cell is skipped, the others still get theirs', () => {
    const {doc, rows} = campaignTable(2);
    rows[0].querySelector = () => null;

    assert.strictEqual(api.addCampaignStatusColumn(doc, ccfg), true);

    assert.strictEqual(rows[0].children.length, 3);
    assert.strictEqual(rows[1].children.length, 4);
});

function statusLabelIn(status) {
    const {doc, rows} = campaignTable(1, true, status);
    api.addCampaignStatusColumn(doc, ccfg);

    return rows[0].label;
}

test('addCampaignStatusColumn: an active label becomes a play icon plus the word, and the word is its title', () => {
    const label = statusLabelIn('published');

    assert.deepStrictEqual(label.children.map((c) => [c.name, c.className, c.textContent]), [['i', 'ri-play-fill', ''], ['span', 'patches-status-label__text', 'Ativo']]);
    assert.strictEqual(label.children[0].attrs['aria-hidden'], 'true');
    assert.strictEqual(label.attrs.title, 'Ativo');
    assert.strictEqual(label.attrs['aria-label'], 'Ativo');
});

test('addCampaignStatusColumn: an inactive label becomes a stop icon plus the word', () => {
    const label = statusLabelIn('unpublished');

    assert.deepStrictEqual(label.children.map((c) => [c.className, c.textContent]), [['ri-stop-fill', ''], ['patches-status-label__text', 'Inativo']]);
    assert.strictEqual(label.attrs.title, 'Inativo');
});

test('addCampaignStatusColumn: a label that is spinning is left as it is for paint() to finish', () => {
    const label = statusLabelIn('busy');

    assert.deepStrictEqual(label.children, []);
    assert.strictEqual(label.attrs.title, undefined);
});
