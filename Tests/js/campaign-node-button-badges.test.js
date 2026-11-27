// Test-tags: areas=campaign risks=UI
// What decorates the node buttons of the campaign builder (Resources/js/campaign-node-button-badges.js): which links get a badge and
// which label, the back button of the panel, no double decoration, and when the observer must run.
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/campaign-node-button-badges.js');

const labels = {clone: 'Clone', edit: 'Edit', remove: 'Remove', back: 'Back'};

function link(classes, matchesBack = false, aria = '') {
    const set = new Set(classes);
    const icon = {className: 'ri-old'};
    const el = {
        children: [], listeners: {},
        classList: {add: (c) => set.add(c), contains: (c) => set.has(c)},
        matches: () => matchesBack,
        querySelector: () => icon,
        getAttribute: () => aria,
        appendChild(c) { el.children.push(c); },
        addEventListener(n, f) { el.listeners[n] = f; },
        icon,
    };
    return el;
}
const doc   = {createElement: () => ({className: '', textContent: ''})};
const scope = (links) => ({querySelectorAll: () => links});

test('clone, edit and remove buttons get the badge class and their label', () => {
    const [c, e, r] = [link(['btn', 'btn-clone']), link(['btn', 'btn-edit']), link(['btn', 'btn-delete'])];
    assert.strictEqual(api.decorate(scope([c, e, r]), labels, doc), 3);
    assert.deepStrictEqual([c, e, r].map((l) => l.children[0].textContent), ['Clone', 'Edit', 'Remove']);
    for (const l of [c, e, r]) {
        assert.ok(l.classList.contains('patches-node-badge'));
        assert.strictEqual(l.children[0].className, 'patches-node-badge__text');
        assert.ok(l.listeners.mouseenter && l.listeners.mouseleave);
    }
});

test('the back button of the panel list becomes a badge with the back icon and label', () => {
    const b = link(['btn'], true);
    assert.strictEqual(api.decorate(scope([b]), labels, doc), 1);
    assert.strictEqual(b.children[0].textContent, 'Back');
    assert.strictEqual(b.icon.className, 'ri-arrow-go-back-line');
});

test('a button already decorated is not decorated twice', () => {
    const c = link(['btn', 'btn-clone']);
    api.decorate(scope([c]), labels, doc);
    assert.strictEqual(api.decorate(scope([c]), labels, doc), 0);
    assert.strictEqual(c.children.length, 1);
});

test('an unknown button is left alone', () => {
    const x = link(['btn', 'btn-other']);
    assert.strictEqual(api.decorate(scope([x]), labels, doc), 0);
    assert.strictEqual(x.children.length, 0);
    assert.ok(!x.classList.contains('patches-node-badge'));
});

test('a missing label falls back to the aria-label', () => {
    const c = link(['btn', 'btn-edit'], false, ' Editar ');
    api.decorate(scope([c]), {}, doc);
    assert.strictEqual(c.children[0].textContent, 'Editar');
});

test('hasButtons is true for added nodes that are or contain buttons / panel, false for text and others', () => {
    const el = (self, inner) => ({nodeType: 1, matches: () => self, querySelector: () => inner});
    assert.ok(api.hasButtons([el(true, null)]));
    assert.ok(api.hasButtons([el(false, {})]));
    assert.ok(!api.hasButtons([el(false, null), {nodeType: 3}]));
});
