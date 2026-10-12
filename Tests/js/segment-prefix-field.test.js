// Runs the REAL script that SegmentPrefixFieldSubscriber puts on the segment form (taken from the PHP class, not copied)
// against a small fake mQuery: the name input is split into Prefixo and Nome, and the real input always holds the join.
//
// Run inside the Mautic container, no extra dependencies (Node's built-in runner):
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD   = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/SegmentPrefixFieldSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\SegmentPrefixFieldSubscriber::script('Prefixo', 'Selecione o prefixo', ['Compras', 'Vendas']);`,
], {encoding: 'utf8'});

// A tiny jQuery stand-in: elements are plain objects, mQuery(...) wraps them.
function load(initialName, {present = true} = {}) {
    const created = [];
    const makeEl = (attrs = {}) => {
        const el = {attrs, value: '', handlers: {}, hidden: false, after: null};
        el.api = {
            length: 1,
            data: (k, v) => (v === undefined ? el.attrs['data-' + k] : (el.attrs['data-' + k] = v)),
            val: (v) => { if (v === undefined) { return el.value; } el.value = v; return el.api; },
            css: () => el.api,
            on: (types, fn) => { types.split(' ').forEach((t) => { (el.handlers[t] = el.handlers[t] || []).push(fn); }); return el.api; },
            hide: () => { el.hidden = true; return el.api; },
            after: (x) => { el.after = x; return el.api; },
            append: () => el.api,
            text: () => el.api,
        };
        return el;
    };
    const full = makeEl({id: 'leadlist_name'});
    full.value = initialName;
    const inputs = {};
    const options = [];
    const mQuery = (arg, attrs) => {
        if (typeof arg === 'function') { arg(); return; }
        if (arg === '#leadlist_name') { return present ? full.api : {length: 0}; }
        if (arg === '<option>') { const el = makeEl(attrs); options.push(attrs.value); return el.api; }
        if (arg === '<select>') { const el = makeEl(attrs); inputs[attrs.id] = el; created.push(el); return el.api; }
        if (arg === '<input>') { const el = makeEl(attrs); inputs[attrs.id] = el; created.push(el); return el.api; }
        return makeEl(attrs).api;
    };
    mQuery.each = (arr, fn) => arr.forEach((v, i) => fn(i, v));
    mQuery.trim = (s) => String(s).trim();
    const Mautic = {onPageLoad() { return 'loaded'; }};
    const context = {mQuery, Mautic, window: {}};
    vm.runInNewContext(SCRIPT, context);

    const type = (id, value) => { inputs[id].value = value; (inputs[id].handlers.input || inputs[id].handlers.change).forEach((fn) => fn()); };

    return {full, inputs, created, options, Mautic, context, type};
}

test('splits a name with prefix into the two inputs', () => {
    const {full, inputs} = load('Compras | Onboarding');
    assert.strictEqual(inputs.leadlist_name_prefix.value, 'Compras');
    assert.strictEqual(inputs.leadlist_name_rest.value, 'Onboarding');
    assert.strictEqual(full.hidden, true);
    assert.strictEqual(full.value, 'Compras | Onboarding');
});

test('a name with no prefix stays whole in Nome and the prefix is empty', () => {
    const {full, inputs} = load('Onboarding');
    assert.strictEqual(inputs.leadlist_name_prefix.value, '');
    assert.strictEqual(inputs.leadlist_name_rest.value, 'Onboarding');
    assert.strictEqual(full.value, 'Onboarding');
});

test('typing in either input updates the real name input', () => {
    const {full, type} = load('');
    type('leadlist_name_prefix', 'Vendas');
    type('leadlist_name_rest', 'Onboarding');
    assert.strictEqual(full.value, 'Vendas | Onboarding');
});

test('only the name typed gives a value that the server refuses (no prefix)', () => {
    const {full, type} = load('');
    type('leadlist_name_rest', 'Onboarding');
    assert.strictEqual(full.value, ' | Onboarding');
});

test('both inputs empty leave the real input empty (core says the name is required)', () => {
    const {full, type} = load('A | B');
    type('leadlist_name_prefix', '');
    type('leadlist_name_rest', '');
    assert.strictEqual(full.value, '');
});

test('does nothing on a page without the name input, and acts again after an ajax page load', () => {
    const none = load('', {present: false});
    assert.strictEqual(none.created.length, 0);

    const {created, Mautic} = load('A | B');
    assert.strictEqual(created.length, 2);
    assert.strictEqual(Mautic.onPageLoad(), 'loaded');
    assert.strictEqual(created.length, 2, 'the same input is not split twice');
});

test('the select has an empty option and the registered prefixes', () => {
    const {options} = load('');
    assert.deepStrictEqual(options, ['', 'Compras', 'Vendas']);
});

test('a prefix of the segment that is no longer registered stays as an option', () => {
    const {options, inputs} = load('Entregas | Onboarding');
    assert.deepStrictEqual(options, ['', 'Compras', 'Vendas', 'Entregas']);
    assert.strictEqual(inputs.leadlist_name_prefix.value, 'Entregas');
});
