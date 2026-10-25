// What is stripped from the node buttons of the campaign builder (Resources/js/campaign-node-button-tooltips.js). The real behaviour (the
// mouse, Bootstrap, the clone, the edit modal) is checked in the browser (scripts/5/e2e/tests/campaign-node-tooltip.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/campaign-node-button-tooltips.js');

// an icon of a button: the attributes core gives it after Bootstrap took the title
function icon(describedby) {
    const attrs = {'data-toggle': 'tooltip', title: '', 'data-original-title': 'Editar', 'data-placement': 'top'};
    if (describedby) { attrs['aria-describedby'] = describedby; }
    return {
        attrs,
        getAttribute(name) { return name in attrs ? attrs[name] : null; },
        removeAttribute(name) { delete attrs[name]; },
    };
}
const scope = (icons) => ({querySelectorAll: (selector) => { scope.selector = selector; return icons; }});
const doc   = (tips = {}) => ({getElementById: (id) => tips[id] || null});

test('the selector reaches only the icons of the node buttons that have a tooltip', () => {
    const s = scope([]);
    api.strip(s, null, doc());
    assert.strictEqual(scope.selector, '.campaign-event-buttons [data-toggle="tooltip"]');
});

test('the attributes that make the tooltip are removed, and the number stripped is returned', () => {
    const a = icon();
    const b = icon();
    assert.strictEqual(api.strip(scope([a, b]), null, doc()), 2);
    assert.deepStrictEqual(a.attrs, {});
    assert.deepStrictEqual(b.attrs, {});
});

test('the tooltip is destroyed through Bootstrap before the attributes go', () => {
    const a = icon();
    const calls = [];
    const jq = (el) => ({tooltip: (action) => calls.push([el, action, Object.keys(el.attrs).length])});
    api.strip(scope([a]), jq, doc());
    assert.deepStrictEqual(calls, [[a, 'destroy', 4]]);
});

test('a tooltip that is up is taken out of the page and the link to it is removed', () => {
    const a = icon('tooltip42');
    const tip = {parentNode: {removeChild(el) { tip.removed = el === tip; }}};
    api.strip(scope([a]), null, doc({tooltip42: tip}));
    assert.strictEqual(tip.removed, true);
    assert.strictEqual('aria-describedby' in a.attrs, false);
});

test('the plugin of Bootstrap missing is not an error', () => {
    const jq = () => ({tooltip() { throw new Error('tooltip is not a function'); }});
    assert.doesNotThrow(() => api.strip(scope([icon()]), jq, doc()));
});

test('nothing to strip is not an error and returns zero', () => {
    assert.strictEqual(api.strip(scope([]), null, doc()), 0);
});

// the nodes just added to the page: only the ones that are, or hold, node buttons make the page worth stripping again
const element = (matches, holds) => ({nodeType: 1, matches: () => matches, querySelector: () => holds});

test('a node added to the page that is or holds node buttons is noticed', () => {
    assert.strictEqual(api.hasButtons([element(true, null)]), true);
    assert.strictEqual(api.hasButtons([element(false, {})]), true);
});

test('other nodes added to the page are not noticed', () => {
    assert.strictEqual(api.hasButtons([element(false, null), {nodeType: 3}]), false);
    assert.strictEqual(api.hasButtons([]), false);
});
