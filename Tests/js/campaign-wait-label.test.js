// Runs the REAL script that CampaignBuilderWaitLabelSubscriber puts on every admin page (it replaces core's
// Mautic.campaignBuilderUpdateLabel: the wait goes in a strip on the node) against a fake jsPlumb connection whose getOverlays() returns an object keyed by id,
// like jsPlumb 2.15.6 does.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script(rewrites = []) {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignBuilderWaitLabelSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignBuilderWaitLabelSubscriber::script(json_decode(${JSON.stringify(JSON.stringify(rewrites))}, true));`,
    ], {encoding: 'utf8'});
}

// A connection like jsPlumb 2.15.6: overlays kept in an object by id; removeOverlay deletes the entry.
function connection(overlays) {
    return {
        sourceId: 'src', targetId: 'CampaignEvent_1', overlays: {...overlays},
        getOverlays() { return this.overlays; },
        removeOverlay(id) { delete this.overlays[id]; },
        addOverlay() { throw new Error('no balloon must be drawn on the line'); },
    };
}

// A node: .campaign-event-type holding a first child (the card); a strip is created with createElement and inserted before it.
function fakeNode() {
    const children = [{className: 'campaign-event-content'}];
    const type = {
        children,
        querySelector(sel) { return sel.includes('campaign-event-wait') ? children.find((c) => c.className === 'campaign-event-wait') || null : null; },
        get firstChild() { return children[0]; },
        insertBefore(el, ref) { children.splice(children.indexOf(ref), 0, el); },
    };
    const node = {querySelector: () => type, getAttribute: () => 'action'};

    return {node, type};
}

function load(conn, labels, node, rewrites = []) {
    const revalidated = [];
    const Mautic = {
        campaignBuilderLabels: labels,
        campaignBuilderInstance: {select: () => ({each: (fn) => [conn].forEach(fn)}), revalidate: (n) => revalidated.push(n)},
        campaignBuilderUpdateLabel() { throw new Error('core version must have been replaced'); },
        onPageLoad() { return 'core'; },
    };
    const ready = [];
    const frames = [];
    const flush = () => { while (frames.length) frames.shift()(); };
    const document = {
        head: {appendChild() {}},
        createElement() {
            const el = {className: '', lastChild: {textContent: ''}, remove() { node.type.children.splice(node.type.children.indexOf(el), 1); }};
            Object.defineProperty(el, 'innerHTML', {set(v) { el.html = v; }});

            return el;
        },
        getElementById: () => node.node,
    };
    vm.runInNewContext(script(rewrites), {Mautic, window: {}, document, mQuery: (fn) => ready.push(fn), requestAnimationFrame: (fn) => frames.push(fn)});
    ready.forEach((fn) => fn());

    return {Mautic, revalidated, flush};
}

const strips = (node) => node.type.children.filter((c) => c.className === 'campaign-event-wait');

test('the balloon of the line is removed (overlays as an object) and nothing is drawn on the line', () => {
    const conn = connection({
        'src_CampaignEvent_1_connectionLabel': {type: 'Label', id: 'src_CampaignEvent_1_connectionLabel'},
        arrow: {type: 'Arrow', id: 'arrow'},
    });
    const n = fakeNode();
    const {Mautic} = load(conn, {CampaignEvent_1: 'esperar 2 dias'}, n);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.deepStrictEqual(Object.keys(conn.overlays), ['arrow']);
});

test('overlays given as an array are handled too', () => {
    const conn = connection({});
    conn.getOverlays = () => [{type: 'Label', id: 'a'}];
    let removed = null;
    conn.removeOverlay = (id) => { removed = id; };
    const {Mautic} = load(conn, {}, fakeNode());

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(removed, 'a');
});

test('the wait goes in one strip, before the card, and is reused when it changes', () => {
    const n = fakeNode();
    const {Mautic, revalidated, flush} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(n.type.children[0].className, 'campaign-event-wait');
    assert.strictEqual(n.type.children[0].lastChild.textContent, 'esperar 2 dias');

    Mautic.campaignBuilderLabels.CampaignEvent_1 = 'esperar 3 dias';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(strips(n)[0].lastChild.textContent, 'esperar 3 dias');
    assert.strictEqual(revalidated.length, 2);

    // the same text again (core calls the function once per connection when the canvas opens): nothing to redo
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(revalidated.length, 2);
});

test('many nodes changed in one moment are revalidated together, once each, on the next frame', () => {
    const a = fakeNode();
    const {Mautic, revalidated, flush} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias', CampaignEvent_2: 'esperar 2 dias'}, a);
    // the fake document answers every id with the same node: three updates, one real change
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_2');
    Mautic.campaignBuilderLabels.CampaignEvent_1 = 'esperar 5 dias';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(revalidated.length, 0, 'nothing is laid out before the frame');
    flush();
    assert.strictEqual(revalidated.length, 1, 'the same node is revalidated once');
});

test('the helper of the Preview tab puts the strip on a given node', () => {
    const n = fakeNode();
    const ctx = {Mautic: {campaignBuilderLabels: {}, campaignBuilderInstance: null, onPageLoad() {}}};
    const document = {head: {appendChild() {}}, createElement() { const el = {className: '', lastChild: {textContent: ''}, remove() {}}; Object.defineProperty(el, 'innerHTML', {set() {}}); return el; }, getElementById: () => null};
    const window = {};
    vm.runInNewContext(script(), {...ctx, window, document, mQuery: () => {}});

    window.mauticPatchesSetWait(n.node, 'esperar 1 minuto');

    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(strips(n)[0].lastChild.textContent, 'esperar 1 minuto');
});

test('no wait: the strip is removed', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n);
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    Mautic.campaignBuilderLabels.CampaignEvent_1 = '';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n).length, 0);
});

test('a source (no .campaign-event-type) is left alone', () => {
    const n = {node: {querySelector: () => null, getAttribute: () => 'source'}, type: {children: []}};
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'x'}, n);

    assert.doesNotThrow(() => Mautic.campaignBuilderUpdateLabel('CampaignEvent_1'));
});

test('the page load of core still runs, and the patch holds after it', () => {
    const {Mautic} = load(connection({}), {}, fakeNode());

    assert.strictEqual(Mautic.onPageLoad(), 'core');
    assert.doesNotThrow(() => Mautic.campaignBuilderUpdateLabel('CampaignEvent_1'));
});

test('the texts of the Preview tab are put on the nodes now and after every Mautic page load, and a new campaign replaces them', () => {
    const preview = fakeNode();
    const win = {};
    const document = {head: {appendChild() {}}, createElement() { const el = {className: '', lastChild: {textContent: ''}, remove() { preview.type.children.splice(preview.type.children.indexOf(el), 1); }}; Object.defineProperty(el, 'innerHTML', {set() {}}); return el; },
        getElementById: () => null, querySelector: (sel) => (sel === '#preview-container #CampaignEvent_172' ? preview.node : null)};
    const Mautic = {campaignBuilderLabels: {}, campaignBuilderInstance: null, onPageLoad() { return 'core'; }};
    vm.runInNewContext(script(), {Mautic, window: win, document, mQuery: () => {}});

    win.mauticPatchesPreviewWaits = {CampaignEvent_172: 'esperar 1 minuto'};
    // the node is not in the page yet (ajax): nothing to do, nothing breaks
    assert.doesNotThrow(() => win.mauticPatchesApplyWaits());
    assert.strictEqual(strips(preview).length, 1, 'now');

    // a Mautic page load applies them again: unchanged, still one strip
    assert.strictEqual(Mautic.onPageLoad(), 'core');
    assert.strictEqual(strips(preview).length, 1);
    assert.strictEqual(strips(preview)[0].lastChild.textContent, 'esperar 1 minuto');
});

const REWRITES = [
    {from: 'entre %number% %unit%', to: 'esperar %number% %unit%'},
    {from: 'por %full%', to: 'esperar até %full%'},
];

test('after a condition the inaction text ("entre 1 dia") is turned back into the plain one', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'entre 1 dia'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n)[0].lastChild.textContent, 'esperar 1 dia');
});

test('the date text of the no path after a condition is turned back too', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'por 10 de outubro de 2026 às 09:00'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n)[0].lastChild.textContent, 'esperar até 10 de outubro de 2026 às 09:00');
});

test('after a decision the inaction text stays: it is a deadline there', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'decision';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'entre 1 dia'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n)[0].lastChild.textContent, 'entre 1 dia');
});

test('a text that is not the inaction one is left alone after a condition', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n)[0].lastChild.textContent, 'esperar 2 dias');
});
