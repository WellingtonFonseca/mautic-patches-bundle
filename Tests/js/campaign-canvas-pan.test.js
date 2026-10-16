// Runs the REAL script that CampaignCanvasPanSubscriber puts on every admin page (middle button drags the campaign canvas)
// against a small fake document: a scroll container inside #campaign-builder and the listeners it registers.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script() {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignCanvasPanSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignCanvasPanSubscriber::script();`,
    ], {encoding: 'utf8'});
}

// body > builder (#campaign-builder, no scroll) > content (scrolls) > node. `closest` knows the three ids.
function page() {
    const classes = new Set();
    const body = {classList: {add: (c) => classes.add(c), remove: (c) => classes.delete(c)}, parentElement: null};
    const builder = {id: 'campaign-builder', parentElement: body, scrollWidth: 100, clientWidth: 100, scrollHeight: 100, clientHeight: 100, style: {overflowX: 'visible', overflowY: 'visible'}};
    const content = {parentElement: builder, scrollWidth: 10000, clientWidth: 1600, scrollHeight: 10000, clientHeight: 1000, scrollLeft: 500, scrollTop: 300, style: {overflowX: 'auto', overflowY: 'auto'}};
    const node = {parentElement: content, style: {overflowX: 'visible', overflowY: 'visible'}};
    const outside = {parentElement: body, style: {overflowX: 'visible', overflowY: 'visible'}};
    for (const el of [builder, content, node, outside]) {
        el.closest = (sel) => { for (let e = el; e; e = e.parentElement) { if (sel === '#' + e.id) return e; } return null; };
    }
    const handlers = {};
    const document = {
        body, head: {appendChild() {}}, createElement: () => ({}),
        addEventListener: (type, fn) => { handlers[type] = fn; },
    };
    const win = {addEventListener: (type, fn) => { handlers['window:' + type] = fn; }};
    const getComputedStyle = (el) => el.style;
    vm.runInNewContext(script(), {document, window: win, getComputedStyle});

    return {handlers, classes, content, node, outside, builder};
}

const down = (target, button = 1, x = 800, y = 500) => {
    const ev = {button, target, clientX: x, clientY: y, prevented: false, preventDefault() { this.prevented = true; }};

    return ev;
};

test('a middle press on a node starts the drag, cancels the browser auto-scroll and shows the closed hand', () => {
    const p = page();
    const ev = down(p.node);

    p.handlers.mousedown(ev);

    assert.strictEqual(ev.prevented, true);
    assert.ok(p.classes.has('mautic-patches-panning'));
});

test('moving the mouse scrolls the nearest scrollable ancestor the other way, by the same distance', () => {
    const p = page();
    p.handlers.mousedown(down(p.node));

    p.handlers.mousemove({buttons: 4, clientX: 600, clientY: 450});

    assert.strictEqual(p.content.scrollLeft, 700, '200 px to the left: the content moves 200 px right of the view');
    assert.strictEqual(p.content.scrollTop, 350);
});

test('releasing the button ends it: further moves scroll nothing, the cursor is back', () => {
    const p = page();
    p.handlers.mousedown(down(p.node));
    p.handlers.mouseup({button: 1});

    p.handlers.mousemove({buttons: 4, clientX: 100, clientY: 100});

    assert.strictEqual(p.content.scrollLeft, 500);
    assert.ok(!p.classes.has('mautic-patches-panning'));
});

test('a move without the middle button held (released outside the window) ends it', () => {
    const p = page();
    p.handlers.mousedown(down(p.node));

    p.handlers.mousemove({buttons: 0, clientX: 100, clientY: 100});

    assert.strictEqual(p.content.scrollLeft, 500);
    assert.ok(!p.classes.has('mautic-patches-panning'));
});

test('the left button and the right button do nothing', () => {
    for (const button of [0, 2]) {
        const p = page();
        const ev = down(p.node, button);
        p.handlers.mousedown(ev);
        assert.strictEqual(ev.prevented, false);
        assert.ok(!p.classes.has('mautic-patches-panning'));
    }
});

test('a middle press outside the campaign canvas is left alone', () => {
    const p = page();
    const ev = down(p.outside);

    p.handlers.mousedown(ev);

    assert.strictEqual(ev.prevented, false);
});

test('the middle auxclick on the canvas is cancelled (no new tab from a link), elsewhere it is not', () => {
    const p = page();
    const onCanvas = {button: 1, target: p.node, prevented: false, preventDefault() { this.prevented = true; }};
    const elsewhere = {button: 1, target: p.outside, prevented: false, preventDefault() { this.prevented = true; }};

    p.handlers.auxclick(onCanvas);
    p.handlers.auxclick(elsewhere);

    assert.strictEqual(onCanvas.prevented, true);
    assert.strictEqual(elsewhere.prevented, false);
});
