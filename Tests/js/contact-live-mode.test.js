// Runs the REAL script that ContactLiveModeSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the live mode button of the contact list gets a spinning icon while it is on (class "active",
// which core's toggleLiveLeadListUpdate sets and removes) and the normal icon again when it is off.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';
const SCRIPT   = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/ContactLiveModeSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\ContactLiveModeSubscriber::script();`,
], {encoding: 'utf8'});

function classes(initial = []) {
    const set = new Set(initial);

    return {add: (c) => set.add(c), remove: (c) => set.delete(c), contains: (c) => set.has(c), has: (c) => set.has(c)};
}

// The button (core's #liveModeButton) with its <i> icon; core's toggle only adds / removes "active" on the button.
function page() {
    const icon = {
        classList: classes(['ri-refresh-line']),
        children: [],
        querySelector(sel) { return sel === '.mautic-patches-live-spin' ? (this.children[0] || null) : null; },
        insertAdjacentHTML(where, html) {
            assert.strictEqual(where, 'beforeend');
            const self = this;
            self.children.push({html, remove() { self.children.splice(self.children.indexOf(this), 1); }});
        },
    };
    const button = {
        classList: classes(['btn']),
        querySelector: (sel) => (sel === 'i' ? icon : null),
        closest(sel) { return sel === '#liveModeButton' ? button : null; },
    };
    const head = {children: [], appendChild(el) { this.children.push(el); }};
    let click;
    const document = {
        addEventListener: (type, fn) => { assert.strictEqual(type, 'click'); click = fn; },
        createElement: () => ({}),
        head,
    };
    const window = {};
    vm.runInNewContext(SCRIPT, {document, window});

    return {
        button, icon, head,
        clickOn: (target) => click({target}),
        toggle() { // what core's onclick does, then the click reaches the document
            button.classList.has('active') ? button.classList.remove('active') : button.classList.add('active');
            click({target: button});
        },
    };
}

test('on: the icon is replaced by a spinning loader', () => {
    const p = page();
    p.toggle();

    assert.strictEqual(p.icon.children.length, 1);
    assert.match(p.icon.children[0].html, /<svg/);
    assert.match(p.icon.children[0].html, /mautic-patches-live-spin/);
    assert.match(p.icon.children[0].html, /animation:ri-spin 1s linear infinite/);
    assert.ok(p.icon.classList.contains('mautic-patches-live-on'), 'the font glyph is hidden by this class');
});

test('off: the spinner goes away and the normal icon is back', () => {
    const p = page();
    p.toggle();
    p.toggle();

    assert.strictEqual(p.icon.children.length, 0);
    assert.ok(!p.icon.classList.contains('mautic-patches-live-on'));
    assert.ok(p.icon.classList.contains('ri-refresh-line'), 'the icon itself is never removed');
});

test('on again after off spins again, and one click never makes two spinners', () => {
    const p = page();
    p.toggle(); p.toggle(); p.toggle();
    assert.strictEqual(p.icon.children.length, 1);
    p.clickOn(p.button);
    assert.strictEqual(p.icon.children.length, 1, 'a click that did not change the state changes nothing');
});

test('a click somewhere else does nothing', () => {
    const p = page();
    p.button.classList.add('active');
    p.clickOn({closest: () => null});

    assert.strictEqual(p.icon.children.length, 0);
});

test('the style that hides the glyph is added once, even if the script runs twice', () => {
    const p = page();
    assert.strictEqual(p.head.children.length, 1);
    assert.match(p.head.children[0].textContent, /\.mautic-patches-live-on::before\{content:none!important\}/);
});
