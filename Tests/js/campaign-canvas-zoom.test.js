// The maths and the wiring of the zoom of the campaign builder canvas (Resources/js/campaign-canvas-zoom.js). The real
// effect (lines, drags, scroll) is checked in the browser (scripts/5/e2e/tests/campaign-canvas-zoom.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const zoom   = require('../../Resources/js/campaign-canvas-zoom.js');

test('clamp keeps the zoom between 30% and 200% and on whole percents', () => {
    assert.strictEqual(zoom.clamp(0.1), 0.3);
    assert.strictEqual(zoom.clamp(5), 2);
    assert.strictEqual(zoom.clamp(1.2345), 1.23);
});

test('a notch of the wheel is one step, up zooms in and down zooms out', () => {
    assert.strictEqual(zoom.wheelZoom(1, -100), 1.1);
    assert.strictEqual(zoom.wheelZoom(1, 100), 0.9);
});

test('a big wheel delta is still one step and a small one (a pinch) is a part of it', () => {
    assert.strictEqual(zoom.wheelZoom(1, -500), 1.1);
    assert.strictEqual(zoom.wheelZoom(1, -50), 1.05);
});

test('the wheel stops at the limits', () => {
    assert.strictEqual(zoom.wheelZoom(2, -100), 2);
    assert.strictEqual(zoom.wheelZoom(0.3, 100), 0.3);
});

test('the point under the mouse stays on the same spot of the canvas', () => {
    // canvas spot under the point before: (scroll + point) / from; after: (scroll' + point) / to. Both are equal.
    const scroll = 400, point = 250, from = 1, to = 1.5;
    const next = zoom.anchoredScroll(scroll, point, from, to);
    assert.strictEqual((next + point) / to, (scroll + point) / from);
});

test('with the point at the corner the scroll only grows by the ratio', () => {
    assert.strictEqual(zoom.anchoredScroll(200, 0, 1, 2), 400);
});

test('fit: a campaign that is bigger than the view is scaled down to show every node, centred', () => {
    const rects = [{x: 100, y: 100, width: 200, height: 100}, {x: 1700, y: 900, width: 200, height: 100}];
    const view = {width: 1000, height: 600};
    const r = zoom.fitView(rects, view, 60);
    // the box is 1800 x 1000 plus the padding: 1920 x 1020
    assert.strictEqual(r.zoom, zoom.clamp(Math.min(1000 / 1920, 600 / 1020)));
    assert.strictEqual(r.zoom, 0.52);
    // after the scale the first node is at (100 - 60) * zoom of the canvas, the box is centred in the view
    assert.ok(Math.abs(r.left - ((100 - 60) * r.zoom - (1000 - 1920 * r.zoom) / 2)) < 1e-9);
    // the box is 530 px high in a 600 px view: the centred top would be negative, the scroll stops at 0
    assert.strictEqual(r.top, 0);
});

test('fit: a small campaign stays at 100% and is not blown up', () => {
    const r = zoom.fitView([{x: 500, y: 500, width: 200, height: 100}], {width: 1600, height: 900}, 60);
    assert.strictEqual(r.zoom, 1);
});

test('fit: never scrolls to a negative position and never goes under the minimum', () => {
    const r = zoom.fitView([{x: 0, y: 0, width: 20000, height: 20000}], {width: 1000, height: 600}, 60);
    assert.strictEqual(r.zoom, 0.3);
    assert.ok(r.left >= 0 && r.top >= 0);
});

test('fit: a box narrower than the view is centred, and the scroll stops at 0 on the left too', () => {
    const r = zoom.fitView([{x: 0, y: 0, width: 200, height: 100}], {width: 1000, height: 600}, 60);
    assert.strictEqual(r.left, 0);
    assert.strictEqual(r.top, 0);
});

test('the keys: + and = zoom in, - and _ zoom out, 0 resets, any other key is not a zoom key', () => {
    assert.strictEqual(zoom.keyAction('+'), 'in');
    assert.strictEqual(zoom.keyAction('='), 'in');
    assert.strictEqual(zoom.keyAction('-'), 'out');
    assert.strictEqual(zoom.keyAction('_'), 'out');
    assert.strictEqual(zoom.keyAction('0'), 'reset');
    assert.strictEqual(zoom.keyAction('a'), null);
});

// a minimal page: the wheel listener, the toolbar and the canvas
function page(withPreview) {
    const listeners = {};
    const options = {};
    const group = [];
    const canvas = {style: {}};
    const nodes = [];
    const view = {clientWidth: 1000, clientHeight: 600, scrollLeft: 0, scrollTop: 0,
        contains: () => true, getClientRects: () => [1], getBoundingClientRect: () => ({left: 0, top: 0})};
    // the Preview tab of the campaign page: its own box that scrolls, no builder on that page
    const inserted = [];
    const preview = withPreview ? {clientWidth: 500, clientHeight: 500, scrollLeft: 0, scrollTop: 0,
        contains: () => true, getClientRects: () => [1], getBoundingClientRect: () => ({left: 0, top: 0}),
        querySelector: () => null, insertBefore: (el) => inserted.push(el), firstChild: null} : null;
    // the builder's header of Mautic 7 (.header__global): the group goes in before its first button
    const bar = {style: {}, querySelector: () => null, firstChild: null, insertBefore: (el) => group.push(el)};
    const doc = {
        head: {appendChild() {}},
        createElement: () => ({style: {}, appendChild(c) { (this.children = this.children || []).push(c); }, addEventListener(n, f) { this.click = f; }, setAttribute() {}}),
        getElementById: (id) => (id === 'CampaignCanvas' ? canvas : (id === 'preview-container' ? preview : null)),
        querySelectorAll: () => nodes,
        querySelector: (s) => {
            if (withPreview) { return null; }

            return s.indexOf('header__global') > -1 ? bar : view;
        },
        addEventListener: (name, fn, opts) => { listeners[name] = fn; options[name] = opts; },
    };
    const calls = [];
    const Mautic = {onPageLoad() {}, prepareCampaignCanvas() {}, campaignBuilderInstance: {setZoom: (z) => calls.push(['zoom', z]), repaintEverything: () => calls.push(['repaint'])}};
    const root = {document: doc, getComputedStyle: () => ({position: 'static'})};
    return {inserted, preview, listeners, options, nodes, group, canvas, view, Mautic, calls, root, bar};
}

function installed(withPreview) {
    const p = page(withPreview);
    const saved = global.window;
    // the script reads `window` once, when it is loaded; the page is handed through a fresh copy of the module
    delete require.cache[require.resolve('../../Resources/js/campaign-canvas-zoom.js')];
    global.window = p.root;
    const mod = require('../../Resources/js/campaign-canvas-zoom.js');
    global.window = saved;
    mod.install.call(null, p.Mautic, {zoomIn: 'in', zoomOut: 'out', zoomReset: 'reset'});
    return p;
}

test('CTRL + wheel scales the canvas, tells jsPlumb and cancels the browser zoom', () => {
    const p = installed();
    let prevented = false;
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() { prevented = true; }});
    assert.ok(prevented);
    assert.strictEqual(p.canvas.style.transform, 'scale(1.1)');
    assert.strictEqual(p.canvas.style.transformOrigin, '0 0');
    assert.deepStrictEqual(p.calls[0], ['zoom', 1.1]);
});

test('the wheel without CTRL is left alone', () => {
    const p = installed();
    let prevented = false;
    p.listeners.wheel({ctrlKey: false, deltaY: -100, target: {}, preventDefault() { prevented = true; }});
    assert.ok(!prevented);
    assert.strictEqual(p.canvas.style.transform, undefined);
});

test('CTRL + "+" and CTRL + "0" zoom and reset while the builder is open', () => {
    const p = installed();
    let prevented = 0;
    const key = (k) => p.listeners.keydown({key: k, ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() { prevented++; }});
    key('+');
    assert.strictEqual(p.canvas.style.transform, 'scale(1.1)');
    key('-');
    key('-');
    assert.strictEqual(p.canvas.style.transform, 'scale(0.9)');
    key('0');
    assert.strictEqual(p.canvas.style.transform, 'scale(1)');
    assert.strictEqual(prevented, 4);
});

test('the keys are left alone without CTRL and inside a field', () => {
    const p = installed();
    let prevented = 0;
    const base = {key: '+', target: {tagName: 'DIV'}, preventDefault() { prevented++; }};
    p.listeners.keydown(Object.assign({}, base, {ctrlKey: false}));
    p.listeners.keydown(Object.assign({}, base, {ctrlKey: true, target: {tagName: 'INPUT'}}));
    assert.strictEqual(prevented, 0);
    assert.strictEqual(p.canvas.style.transform, undefined);
});

test('the wheel listener is not passive: the browser zoom must be cancelled', () => {
    const p = installed();
    assert.strictEqual(p.options.wheel.passive, false);
});

test('a meta key (Mac) zooms like CTRL', () => {
    const p = installed();
    p.listeners.keydown({key: '+', ctrlKey: false, metaKey: true, target: {tagName: 'DIV'}, preventDefault() {}});
    assert.strictEqual(p.canvas.style.transform, 'scale(1.1)');
});

test('jsPlumb repaints the lines after a zoom', () => {
    const p = installed();
    p.listeners.keydown({key: '+', ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() {}});
    assert.deepStrictEqual(p.calls, [['zoom', 1.1], ['repaint']]);
});

test('the same zoom again does nothing (no repaint of every line)', () => {
    const p = installed();
    p.listeners.keydown({key: '0', ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() {}});
    assert.deepStrictEqual(p.calls, []);
});

test('a canvas made later (the builder opens) gets the current zoom', () => {
    const p = installed();
    p.listeners.keydown({key: '+', ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() {}});
    p.calls.length = 0;
    p.Mautic.prepareCampaignCanvas();
    assert.deepStrictEqual(p.calls, [['zoom', 1.1], ['repaint']]);
});

test('fit ignores the nodes that are not shown (width 0)', () => {
    const p = installed();
    p.nodes.push({offsetWidth: 200, offsetHeight: 100, offsetLeft: 100, offsetTop: 100});
    p.nodes.push({offsetWidth: 0, offsetHeight: 0, offsetLeft: 90000, offsetTop: 90000});
    const buttons = p.group[0].children;
    buttons[3].click();
    // only the visible node counts: it fits at 100%
    assert.strictEqual(p.canvas.style.transform, 'scale(1)');
});

test('the buttons are put in one group in the toolbar', () => {
    const p = installed();
    assert.strictEqual(p.group.length, 1);
});

test('Preview: the group goes into the box of the Preview as a sticky row, once', () => {
    const p = installed(true);
    assert.strictEqual(p.inserted.length, 1);
    assert.strictEqual(p.inserted[0].className, 'mautic-patches-zoom-sticky');
    assert.strictEqual(p.inserted[0].children[0].className, 'mautic-patches-zoom');
    assert.strictEqual(p.inserted[0].children[0].children.length, 4);
});

test('Preview: CTRL + wheel over the Preview zooms the canvas', () => {
    const p = installed(true);
    let prevented = false;
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() { prevented = true; }});
    assert.ok(prevented);
    assert.strictEqual(p.canvas.style.transform, 'scale(1.1)');
});

test('Preview: the keys only act while the mouse is over the Preview (CTRL + "+" is also the browser zoom)', () => {
    const p = installed(true);
    let prevented = 0;
    const key = () => p.listeners.keydown({key: '+', ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() { prevented++; }});
    key();
    assert.strictEqual(prevented, 0);
    assert.strictEqual(p.canvas.style.transform, undefined);
    p.listeners.mouseover({target: {closest: (s) => (s === '#preview-container' ? {} : null)}});
    key();
    assert.strictEqual(prevented, 1);
    assert.strictEqual(p.canvas.style.transform, 'scale(1.1)');
    p.listeners.mouseover({target: {closest: () => null}});
    key();
    assert.strictEqual(prevented, 1);
});

test('Preview: the canvas of another campaign page starts at 100%', () => {
    const p = installed(true);
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() {}});
    p.calls.length = 0;
    p.Mautic.prepareCampaignCanvas();
    assert.strictEqual(p.canvas.style.transform, 'scale(1)');
    assert.deepStrictEqual(p.calls, [['zoom', 1], ['repaint']]);
});

test('Preview: fit scrolls the box of the Preview', () => {
    const p = installed(true);
    p.nodes.push({offsetWidth: 200, offsetHeight: 100, offsetLeft: 300, offsetTop: 300});
    p.inserted[0].children[0].children[3].click();
    // (300 - 60) minus half of what is left of the 500 px view around the 320 x 220 box
    assert.strictEqual(p.preview.scrollLeft, 150);
    assert.strictEqual(p.preview.scrollTop, 100);
});

test('CTRL + wheel outside the canvas box is left alone', () => {
    const p = installed();
    p.view.contains = () => false;
    let prevented = false;
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() { prevented = true; }});
    assert.ok(!prevented);
    assert.strictEqual(p.canvas.style.transform, undefined);
});

test('a builder that is closed (hidden, no boxes) does not take the keys', () => {
    const p = installed();
    p.view.getClientRects = () => [];
    let prevented = false;
    p.listeners.keydown({key: '+', ctrlKey: true, target: {tagName: 'DIV'}, preventDefault() { prevented = true; }});
    assert.ok(!prevented);
    assert.strictEqual(p.canvas.style.transform, undefined);
});

test('the percentage of a button that left the page (ajax visit) is no longer written', () => {
    const p = installed();
    const reset = p.group[0].children[1];
    assert.strictEqual(reset.textContent, '100%');
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() {}});
    assert.strictEqual(reset.textContent, '110%', 'a button in the page follows the zoom');
    reset.isConnected = false;
    p.listeners.wheel({ctrlKey: true, deltaY: -100, clientX: 0, clientY: 0, target: {}, preventDefault() {}});
    assert.strictEqual(reset.textContent, '110%', 'a detached button is dropped');
});

test('the Preview group is put back when the Preview box is replaced by ajax (Mautic 7 reloads it on a date range change)', () => {
    const p = installed(true);
    p.preview.querySelector = () => (p.inserted.length ? {} : null); // the box holds the group only while it is in `inserted`
    assert.strictEqual(p.inserted.length, 1, 'the group is put in at install');
    p.Mautic.prepareCampaignCanvas();
    assert.strictEqual(p.inserted.length, 1, 'still there: not put twice');
    p.inserted.length = 0; // core replaced the html of #preview-container
    p.Mautic.prepareCampaignCanvas();
    assert.strictEqual(p.inserted.length, 1, 'put back after the replacement');
});

test('the builder group goes in the header of the builder with the flat buttons of Mautic 7', () => {
    const p = installed();
    assert.strictEqual(p.group.length, 1);
    assert.ok(/mautic-patches-zoom--header/.test(p.group[0].className));
    assert.ok(p.group[0].children.every((b) => /btn-ghost/.test(b.className)), 'flat buttons, as the header ones');
});
