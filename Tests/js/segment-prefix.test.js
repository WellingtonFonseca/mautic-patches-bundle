// Runs the REAL scripts that SegmentPrefixFieldSubscriber (moves the Prefixo select before Nome on the segment form) and
// SegmentPrefixColumnSubscriber (adds the Prefixo column to the segment list) put on the page, taken from the PHP classes,
// against a small fake mQuery.
//
// Run inside the Mautic container, no extra dependencies (Node's built-in runner):
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script(file, cls, args = '') {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/' + file))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\${cls}::script(${args});`,
    ], {encoding: 'utf8'});
}

const FIELD  = script('SegmentPrefixFieldSubscriber.php', 'SegmentPrefixFieldSubscriber');
const COLUMN = script('SegmentPrefixColumnSubscriber.php', 'SegmentPrefixColumnSubscriber', "'Prefixo', 'Ordenar por Prefixo crescente', 'Ordenar por Prefixo decrescente'");

// A tiny element with the few jQuery calls the scripts use.
function el(props = {}) {
    const node = {props, data: {}, children: [], before: null, text: null, found: {}};
    node.api = {
        length: 1,
        data: (k, v) => (v === undefined ? node.data[k] : (node.data[k] = v)),
        closest: () => (node.closestNode || {length: 0}).api || {length: 0},
        insertBefore: (other) => { node.insertedBefore = other; return node.api; },
        before: (x) => { node.before = x; return node.api; },
        text: (t) => { node.text = t; return node.api; },
        append: (x) => { node.children.push(x); return node.api; },
        attr: (k) => node.props['data-' + k.replace('data-', '')] ?? node.props[k],
        find: (sel) => node.found[sel] || {length: 0, each() {}},
    };

    return node;
}

test('field: the prefix row moves before the name row, once', () => {
    const prefixRow = el(); const nameRow = el();
    const prefix = el(); prefix.closestNode = prefixRow;
    const name = el(); name.closestNode = nameRow;
    const mQuery = (a) => (typeof a === 'function' ? a() : a === '#leadlist_prefix' ? prefix.api : a === '#leadlist_name' ? name.api : {length: 0});
    const Mautic = {onPageLoad() { return 'loaded'; }};
    vm.runInNewContext(FIELD, {mQuery, Mautic, window: {}});

    assert.strictEqual(prefixRow.insertedBefore, nameRow.api);
    prefixRow.insertedBefore = null;
    assert.strictEqual(Mautic.onPageLoad(), 'loaded');
    assert.strictEqual(prefixRow.insertedBefore, null, 'not moved twice');
});

test('field: nothing happens on a page without the fields', () => {
    const mQuery = (a) => (typeof a === 'function' ? a() : {length: 0});
    vm.runInNewContext(FIELD, {mQuery, Mautic: {onPageLoad() {}}, window: {}});
});

function listPage(prefixes, sort = 'none') {
    const created = [];
    const head = el();
    const rows = prefixes.map((p) => {
        const nameCell = el();
        const mark = el({'data-prefix': p, 'data-sort': sort}); mark.closestNode = nameCell;
        const row = el(); row.found['.segment-prefix-data'] = mark.api;

        return {row, nameCell, mark};
    });
    const table = el();
    table.found['th.col-leadlist-prefix'] = {length: 0};
    table.found['th.col-leadlist-name'] = head.api;
    table.found['.segment-prefix-data'] = {first: () => rows[0].mark.api};
    table.found['tbody tr'] = {each: (fn) => rows.forEach((r) => fn.call(r.row.api))};
    const mQuery = (a, props) => {
        if (typeof a === 'function') { a(); return; }
        if (a === '#leadListTable') { return table.api; }
        if (typeof a === 'object') { return a; }
        const node = el(props); created.push({tag: a, node}); return node.api;
    };
    const Mautic = {onPageLoad() { return 'loaded'; }};
    vm.runInNewContext(COLUMN, {mQuery, Mautic, window: {}});

    return {created, head, rows, table, Mautic};
}

const tagged = (created, tag, cls) => created.filter((c) => c.tag === tag && c.node.props.class === cls);

test('column: a sortable header before Nome and plain text cells before each row name', () => {
    const {created, head, rows} = listPage(['Compras', '']);

    assert.ok(head.before, 'header added before the Nome header');
    const th = created.find((c) => c.tag === '<th>');
    assert.strictEqual(th.node.props['aria-sort'], 'none');
    assert.strictEqual(tagged(created, '<span>', 'fw-b')[0].node.text, 'Prefixo');
    const sorter = created.find((c) => c.tag === '<a>').node.props;
    assert.strictEqual(sorter.onclick, "Mautic.reorderTableData('lead.list','segment_prefix','list','.page-list');");
    assert.strictEqual(sorter['aria-label'], 'Ordenar por Prefixo crescente');
    assert.match(created.find((c) => c.tag === '<i>').node.props.class, /ri-arrow-up-down-line/);

    const cells = tagged(created, '<td>', 'col-leadlist-prefix');
    assert.deepStrictEqual(cells.map((c) => c.node.text), ['Compras', '']);
    assert.ok(rows.every((r) => r.nameCell.before), 'a cell before every name cell');
    assert.strictEqual(tagged(created, '<span>', 'label label-gray').length, 0, 'no badge');
});

test('column: the header shows the order the list has', () => {
    const asc = listPage(['Compras'], 'ascending').created;
    assert.strictEqual(asc.find((c) => c.tag === '<th>').node.props['aria-sort'], 'ascending');
    assert.strictEqual(asc.find((c) => c.tag === '<a>').node.props['aria-label'], 'Ordenar por Prefixo decrescente');
    assert.match(asc.find((c) => c.tag === '<i>').node.props.class, /ri-arrow-up-line/);

    const desc = listPage(['Compras'], 'descending').created;
    assert.strictEqual(desc.find((c) => c.tag === '<th>').node.props['aria-sort'], 'descending');
    assert.match(desc.find((c) => c.tag === '<i>').node.props.class, /ri-arrow-down-line/);
});

test('column: once per drawn table, and acts again after an ajax page load', () => {
    const {created, Mautic, table} = listPage(['Compras']);
    const before = created.length;
    table.found['th.col-leadlist-prefix'] = {length: 1};
    assert.strictEqual(Mautic.onPageLoad(), 'loaded');
    assert.strictEqual(created.length, before, 'a table that already has the column is left alone');
});
