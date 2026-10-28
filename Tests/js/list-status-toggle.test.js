// The logic of the segment status toggle (Resources/js/list-status-toggle.js): what the answer of the server says, and what the
// label and the menu item show for it. The DOM part is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/list-status-toggle.js');

const cfg = {active: 'Ativo', inactive: 'Inativo', activate: 'Ativar', deactivate: 'Desativar'};
/** The badges of a Status cell: the cell holds one box, and the box the badges. */
const items = (td) => td.children[0].children;
const campaignSpec = {table: '#campaignTable', kind: 'campaign'};
const ccfg = Object.assign({column: 'Status', restart: 'Reingresso', global: 'Global', filters: 'Filtros', rebuild: 'Not rebuilt for %count% h', slow: 'Slow build: %count% s'}, cfg);

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
        removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; },
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
function campaignTable(rowCount, labelled = true, status = 'published', restart = false, tableSelector = '#campaignTable') {
    const headRow = node('tr'), menuTh = node('th'), prefixTh = node('th'), nameTh = node('th');
    [menuTh, prefixTh, nameTh].forEach((th) => headRow.appendChild(th));
    const rows = [];
    for (let k = 0; k < rowCount; k++) {
        const row = node('tr'), cells = [node('td'), node('td'), node('td')];
        cells.forEach((c) => row.appendChild(c));
        if (labelled) {
            const label = node('label');
            label.attrs = {'data-status': status};
            if (restart) { label.attrs['data-restart'] = '1'; }
            cells[2].appendChild(label);
            row.label = label;
        }
        row.cells = cells;
        row.querySelector = (selector) => (selector === 'td' ? row.children[0] : (selector === '.patches-status-label' ? row.label || null : null));
        rows.push(row);
    }
    const table = {
        querySelector(selector) {
            if (selector === 'th.col-patches-status') { return headRow.children.find((c) => c.className.includes('col-patches-status')) || null; }
            if (selector === 'thead tr') { return {querySelector: () => headRow.children[0]}; }
            if (selector === '.patches-status-label') { return rows.find((r) => r.label) ? {} : null; }
            return null;
        },
        querySelectorAll: () => rows
    };
    const doc = {
        querySelector: (selector) => (selector === tableSelector ? table : null),
        createElement: (tag) => { const n = node(tag); n.ownerDocument = doc; return n; }
    };
    rows.forEach((row) => { if (row.label) { row.label.ownerDocument = doc; } });

    return {doc, headRow, rows};
}

test('addStatusColumn: a Status header and a cell per row go right after the menu column, and the label moves into its cell', () => {
    const {doc, headRow, rows} = campaignTable(2);

    assert.strictEqual(api.addStatusColumn(doc, campaignSpec, ccfg), true);

    assert.strictEqual(headRow.children.length, 4);
    const th = headRow.children[1];
    assert.match(th.className, /col-patches-status col-campaign-status/);
    assert.strictEqual(th.attrs.scope, 'col');
    assert.strictEqual(th.children[0].children[0].textContent, 'Status');
    assert.strictEqual(th.children[0].style.pointerEvents, 'none', 'the header is not a sort link');
    rows.forEach((row) => {
        assert.strictEqual(row.children.length, 4);
        const cell = row.children[1];
        assert.strictEqual(cell.className, 'col-patches-status col-campaign-status');
        assert.deepStrictEqual(cell.children[0].children, [row.label]);
        assert.strictEqual(cell.children[0].className, 'patches-status-box');
        assert.strictEqual(cell.children[0].style.width, '22px');
        assert.strictEqual(row.label.attrs['data-placed'], '1');
        assert.strictEqual(row.cells[2].children.length, 0, 'the name cell no longer holds the label');
    });
});

test('addStatusColumn: done once per drawing of the table', () => {
    const {doc, headRow} = campaignTable(1);

    assert.strictEqual(api.addStatusColumn(doc, campaignSpec, ccfg), true);
    assert.strictEqual(api.addStatusColumn(doc, campaignSpec, ccfg), false);
    assert.strictEqual(headRow.children.length, 4);
});

test('addStatusColumn: a row without a label still gets an empty cell, so the columns line up', () => {
    const {doc, rows} = campaignTable(2);
    delete rows[1].label;

    api.addStatusColumn(doc, campaignSpec, ccfg);

    assert.strictEqual(rows[1].children.length, 4);
    assert.strictEqual(rows[1].children[1].children.length, 0, 'no label, no box');
    assert.strictEqual(items(rows[0].children[1]).length, 1);
});

test('addStatusColumn: no column while the table has no label yet (the user and email lists make theirs after the draw)', () => {
    const {doc, headRow, rows} = campaignTable(2, false);

    assert.strictEqual(api.addStatusColumn(doc, campaignSpec, ccfg), false);

    assert.strictEqual(headRow.children.length, 3);
    assert.strictEqual(rows[0].children.length, 3);
});

test('addStatusColumn: no campaign table, or a table without a header, is left alone', () => {
    assert.strictEqual(api.addStatusColumn({querySelector: () => null}, campaignSpec, ccfg), false);
    const noHead = {querySelector: (s) => (s === '#campaignTable' ? {querySelector: () => null} : null)};
    assert.strictEqual(api.addStatusColumn(noHead, campaignSpec, ccfg), false);
});

test('addStatusColumn: a row without any cell is skipped, the others still get theirs', () => {
    const {doc, rows} = campaignTable(2);
    rows[0].querySelector = () => null;

    assert.strictEqual(api.addStatusColumn(doc, campaignSpec, ccfg), true);

    assert.strictEqual(rows[0].children.length, 3);
    assert.strictEqual(rows[1].children.length, 4);
});

function statusLabelIn(status, restart = false) {
    const {doc, rows} = campaignTable(1, true, status, restart);
    api.addStatusColumn(doc, campaignSpec, ccfg);

    return rows[0].label;
}

test('addStatusColumn: an active label becomes a play icon plus the word, and the word is its title', () => {
    const label = statusLabelIn('published');

    assert.deepStrictEqual(label.children.map((c) => [c.name, c.className, c.textContent]), [['i', 'ri-play-fill', ''], ['span', 'patches-icon-badge__text', 'Ativo']]);
    assert.strictEqual(label.children[0].attrs['aria-hidden'], 'true');
    assert.strictEqual(label.attrs.title, 'Ativo');
    assert.strictEqual(label.attrs['aria-label'], 'Ativo');
});

test('addStatusColumn: an inactive label becomes a stop icon plus the word', () => {
    const label = statusLabelIn('unpublished');

    assert.deepStrictEqual(label.children.map((c) => [c.className, c.textContent]), [['ri-stop-fill', ''], ['patches-icon-badge__text', 'Inativo']]);
    assert.strictEqual(label.attrs.title, 'Inativo');
});

test('addStatusColumn: a label that is spinning is left as it is for paint() to finish', () => {
    const label = statusLabelIn('busy');

    assert.deepStrictEqual(label.children, []);
    assert.strictEqual(label.attrs.title, undefined);
});

test('addStatusColumn: a campaign that allows reentry gets a reload badge next to the status, icon plus the word as title', () => {
    const {doc, rows} = campaignTable(1, true, 'published', true);

    api.addStatusColumn(doc, campaignSpec, ccfg);

    const cell = rows[0].children[1];
    assert.strictEqual(items(cell).length, 2);
    assert.strictEqual(cell.children[0].style.width, '50px', 'two badges and the gap between them');
    assert.strictEqual(items(cell)[0], rows[0].label, 'the status badge comes first');
    const badge = items(cell)[1];
    assert.strictEqual(badge.name, 'span');
    assert.match(badge.className, /patches-badge/);
    assert.match(badge.className, /patches-badge--restart/);
    assert.match(badge.className, /patches-icon-badge/);
    assert.match(badge.className, /patches-restart-badge/);
    assert.deepStrictEqual(badge.children.map((c) => [c.name, c.className, c.textContent]), [['i', 'ri-arrow-go-back-line', ''], ['span', 'patches-icon-badge__text', 'Reingresso']]);
    assert.strictEqual(badge.children[0].attrs['aria-hidden'], 'true');
    assert.strictEqual(badge.attrs.title, 'Reingresso');
    assert.strictEqual(badge.attrs['aria-label'], 'Reingresso');
});

test('addStatusColumn: a campaign that does not allow reentry has no reentry badge, and an inactive one with reentry still has it', () => {
    const plain = campaignTable(1, true, 'published', false);
    api.addStatusColumn(plain.doc, campaignSpec, ccfg);
    assert.strictEqual(items(plain.rows[0].children[1]).length, 1);

    const off = campaignTable(1, true, 'unpublished', true);
    api.addStatusColumn(off.doc, campaignSpec, ccfg);
    assert.strictEqual(items(off.rows[0].children[1]).length, 2);
});

test('addStatusColumn: a spinning status label still gets the reentry badge', () => {
    const {doc, rows} = campaignTable(1, true, 'busy', true);

    api.addStatusColumn(doc, campaignSpec, ccfg);

    assert.strictEqual(items(rows[0].children[1]).length, 2);
});

test('COLUMNS: the four lists that have a Status column, each with its table and kind', () => {
    assert.deepStrictEqual(api.COLUMNS.map((c) => [c.kind, c.table]), [
        ['segment', '#leadListTable'], ['campaign', '#campaignTable'], ['user', '#userTable'], ['email', 'table.email-list'],
    ]);
});

test('addStatusColumn: every list gets the same column, with its own kind in the class', () => {
    for (const spec of api.COLUMNS) {
        const {doc, headRow, rows} = campaignTable(1, true, 'unpublished', false, spec.table);

        assert.strictEqual(api.addStatusColumn(doc, spec, ccfg), true, spec.kind);

        assert.strictEqual(headRow.children[1].className, `col-patches-status col-${spec.kind}-status pa-0`);
        assert.strictEqual(rows[0].children[1].className, `col-patches-status col-${spec.kind}-status`);
        assert.deepStrictEqual(items(rows[0].children[1])[0].children.map((c) => c.className), ['ri-stop-fill', 'patches-icon-badge__text']);
    }
});

test('addStatusColumns: adds the column of the lists on the page and leaves the others alone', () => {
    const {doc, headRow} = campaignTable(1, true, 'published', false, '#userTable');

    api.addStatusColumns(doc, ccfg);

    assert.strictEqual(headRow.children.length, 4);
    assert.strictEqual(headRow.children[1].className, 'col-patches-status col-user-status pa-0');
    api.addStatusColumns(doc, ccfg);
    assert.strictEqual(headRow.children.length, 4, 'once per drawing of the table');
});

const FLAGS = Object.fromEntries(api.SEGMENT_FLAGS.map((f) => [f.key, f]));

/** A segment row whose name cell holds the icons core prints next to the name; `present` lists the flags that are there. */
function segmentTable(present) {
    const {doc, headRow, rows} = campaignTable(1, true, 'published', false, '#leadListTable');
    const row = rows[0], nameCell = row.cells[2], found = {};
    for (const key of present) {
        const f = FLAGS[key], icon = node('i');
        icon.className = f.coreIcon || f.icon;
        let holder = icon;
        if (f.holder) {
            holder = node('label');
            holder.attrs['data-original-title'] = key === 'rebuild' ? 'This segment has not been rebuilt for 3 hours.' : 'This segment build time is 45 seconds.';
            holder.appendChild(icon);
        }
        nameCell.appendChild(holder);
        found[f.selector] = icon;
    }
    const plain = row.querySelector;
    row.querySelector = (selector) => found[selector] || plain(selector);
    api.addStatusColumn(doc, api.COLUMNS[0], ccfg);

    return {row, status: row.children[1].children[0] || row.children[1], nameCell};
}

const badgeParts = (b) => [b.className.split(' ').filter((c) => /^patches-badge--|^patches-extra-badge/.test(c)).join(' '), b.children[0].className, b.children[1].textContent, b.attrs.title];

test('SEGMENT_FLAGS: the four icons core prints next to the segment name, in the order they are shown', () => {
    assert.deepStrictEqual(api.SEGMENT_FLAGS.map((f) => f.key), ['global', 'filters', 'rebuild', 'slow']);
    assert.deepStrictEqual(api.SEGMENT_FLAGS.map((f) => f.icon), ['ri-group-2-fill', 'ri-filter-2-fill', 'ri-error-warning-line', 'ri-time-line']);
    assert.strictEqual(api.SEGMENT_FLAGS[2].selector, 'i.ri-error-warning-line-circle', 'the icon core prints is found by its own class');
});

test('addStatusColumn (segment): the global and filters icons leave the name cell and become blue badges after the status', () => {
    const {status, nameCell} = segmentTable(['global', 'filters']);

    assert.strictEqual(status.children.length, 3);
    assert.deepStrictEqual(badgeParts(status.children[1]), ['patches-badge--flag patches-extra-badge', 'ri-group-2-fill', 'Global', 'Global']);
    assert.deepStrictEqual(badgeParts(status.children[2]), ['patches-badge--flag patches-extra-badge', 'ri-filter-2-fill', 'Filtros', 'Filtros']);
    assert.strictEqual(nameCell.children.length, 0, 'the icons of core are gone from the name');
});

test('addStatusColumn (segment): the two warnings become red badges with a short word that carries the number of the tooltip of core, and are wide', () => {
    const {status, nameCell} = segmentTable(['rebuild', 'slow']);

    assert.strictEqual(status.children.length, 3);
    assert.deepStrictEqual(badgeParts(status.children[1]), ['patches-badge--failed patches-extra-badge patches-extra-badge--wide', 'ri-error-warning-line', 'Not rebuilt for 3 h', 'Not rebuilt for 3 h']);
    assert.deepStrictEqual(badgeParts(status.children[2]), ['patches-badge--failed patches-extra-badge patches-extra-badge--wide', 'ri-time-line', 'Slow build: 45 s', 'Slow build: 45 s']);
    assert.strictEqual(nameCell.children.length, 0, 'the label holding each warning goes with its icon');
});

test('addStatusColumn (segment): the number of the tooltip is rounded (core prints 72.0219 hours)', () => {
    const t = campaignTable(1, true, 'published', false, '#leadListTable');
    const label = node('label'), icon = node('i');
    label.attrs['data-original-title'] = 'This segment has not been rebuilt for 72.0219 hours.';
    label.appendChild(icon);
    t.rows[0].cells[2].appendChild(label);
    const plain = t.rows[0].querySelector;
    t.rows[0].querySelector = (selector) => (selector === 'i.ri-error-warning-line-circle' ? icon : plain(selector));
    api.addStatusColumn(t.doc, api.COLUMNS[0], ccfg);

    assert.strictEqual(items(t.rows[0].children[1])[1].attrs.title, 'Not rebuilt for 72 h');
});

test('addStatusColumn (segment): a warning whose tooltip has no number falls back to the sentence of core', () => {
    const {row, status} = segmentTable(['rebuild']);
    assert.strictEqual(status.children[1].attrs.title, 'Not rebuilt for 3 h');

    const t = campaignTable(1, true, 'published', false, '#leadListTable');
    const label = node('label'), icon = node('i');
    label.attrs['data-original-title'] = 'Rebuilt a long time ago.';
    label.appendChild(icon);
    t.rows[0].cells[2].appendChild(label);
    const plain = t.rows[0].querySelector;
    t.rows[0].querySelector = (selector) => (selector === 'i.ri-time-line' ? icon : plain(selector));
    api.addStatusColumn(t.doc, api.COLUMNS[0], ccfg);

    assert.strictEqual(items(t.rows[0].children[1])[1].attrs.title, 'Rebuilt a long time ago.');
    assert.ok(row);
});

test('addStatusColumn (segment): a segment with no icons gets only the status badge', () => {
    const {status} = segmentTable([]);

    assert.strictEqual(status.children.length, 1);
});

test('addStatusColumn (segment): all four together keep the order global, filters, rebuild, slow', () => {
    const {status} = segmentTable(['slow', 'filters', 'rebuild', 'global']);

    assert.deepStrictEqual(status.children.slice(1).map((b) => b.children[0].className), ['ri-group-2-fill', 'ri-filter-2-fill', 'ri-error-warning-line', 'ri-time-line']);
});

test('addStatusColumn: the other lists never look for the segment icons', () => {
    const {doc, rows} = campaignTable(1, true, 'published', false, '#campaignTable');
    const asked = [];
    const plain = rows[0].querySelector;
    rows[0].querySelector = (selector) => { asked.push(selector); return plain(selector); };

    api.addStatusColumn(doc, campaignSpec, ccfg);

    assert.ok(!asked.some((s) => /ri-group-2-fill|ri-filter-2-fill|ri-time-line|ri-error-warning/.test(s)), asked.join(','));
});

test('the reentry badge carries the shared extra-badge class too', () => {
    const {doc, rows} = campaignTable(1, true, 'published', true);

    api.addStatusColumn(doc, campaignSpec, ccfg);

    assert.match(items(rows[0].children[1])[1].className, /patches-extra-badge/);
});

test('pushPlan: the opening badge widens its own column by what it grows, every other column keeps its width, and the table grows by the same', () => {
    const plan = api.pushPlan([40, 90, 60, 300, 80], 1, 118);

    assert.deepStrictEqual(plan.widths, [40, 208, 60, 300, 80]);
    assert.strictEqual(plan.total, 40 + 208 + 60 + 300 + 80);
});

test('pushPlan: nothing to push (a badge that does not grow) leaves every width as it was', () => {
    assert.deepStrictEqual(api.pushPlan([10, 20], 0, 0), {widths: [10, 20], total: 30});
    assert.deepStrictEqual(api.pushPlan([10, 20], 0, -5), {widths: [10, 20], total: 30});
});

test('growth: a badge grows to its content, up to 140px, or 260px when it is a wide one, minus the 22px it already has', () => {
    assert.strictEqual(api.growth({scrollWidth: 60, offsetWidth: 22, className: 'patches-extra-badge'}), 38);
    assert.strictEqual(api.growth({scrollWidth: 400, offsetWidth: 22, className: 'patches-extra-badge'}), 118);
    assert.strictEqual(api.growth({scrollWidth: 400, offsetWidth: 22, className: 'patches-extra-badge patches-extra-badge--wide'}), 238);
    assert.strictEqual(api.growth({scrollWidth: 22, offsetWidth: 22, className: 'patches-status-label'}), 0);
});

test('overflow: the columns are pushed only by what the open badge does not fit in the cell', () => {
    // the box (what the badges take at rest) plus what the badge grows, against the room of the cell
    assert.strictEqual(api.overflow(22, 118, 90), 50);
    assert.strictEqual(api.overflow(50, 38, 90), 0, 'it fits exactly');
    assert.strictEqual(api.overflow(22, 38, 90), 0, 'it fits with room to spare');
    assert.strictEqual(api.overflow(78, 118, 90), 106);
    assert.strictEqual(api.overflow(22, 0, 90), 0);
});
