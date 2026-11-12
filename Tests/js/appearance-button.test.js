// The choices of the Appearance button (Resources/js/appearance-button.js). The button, the modal and the radios are checked in the
// browser (scripts/5/e2e/tests/appearance-modal.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/appearance-button.js');

function fakeDoc(attributes) {
    const attrs = Object.assign({}, attributes);
    return {documentElement: {getAttribute: (n) => (n in attrs ? attrs[n] : null), setAttribute: (n, v) => { attrs[n] = v; }}, attrs};
}

function fakeStorage(data) {
    const store = Object.assign({}, data);
    return {getItem: (k) => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = v; }, store};
}

test('iconFor: a moon on the light themes, a sun on the three dark ones', () => {
    for (const t of ['light', 'solarized-light', null, 'unknown']) { assert.strictEqual(api.iconFor(t), 'ri-moon-line', String(t)); }
    for (const t of ['dark', 'solarized-dark', 'freire']) { assert.strictEqual(api.iconFor(t), 'ri-sun-line', t); }
});

test('currentChoice: storage wins, then the attribute of <html>, then the default', () => {
    const doc = fakeDoc({theme: 'dark'});
    assert.strictEqual(api.currentChoice(doc, fakeStorage({'m-toggle-setting-theme': 'freire'}), 'theme'), 'freire');
    assert.strictEqual(api.currentChoice(doc, fakeStorage({}), 'theme'), 'dark');
    assert.strictEqual(api.currentChoice(fakeDoc({}), fakeStorage({}), 'theme'), 'light');
    assert.strictEqual(api.currentChoice(fakeDoc({}), fakeStorage({}), 'accent'), '01');
});

test('currentChoice: a storage that throws falls back to the attribute', () => {
    const broken = {getItem() { throw new Error('blocked'); }};
    assert.strictEqual(api.currentChoice(fakeDoc({accent: '05'}), broken, 'accent'), '05');
});

test('applyChoice: sets the attribute and the key core reads on every page load', () => {
    const doc = fakeDoc({}), storage = fakeStorage({});
    api.applyChoice(doc, storage, 'theme', 'dark');
    assert.strictEqual(doc.attrs.theme, 'dark');
    assert.strictEqual(storage.store['m-toggle-setting-theme'], 'dark');
});

test('applyChoice: a storage that throws still applies the attribute', () => {
    const doc = fakeDoc({});
    api.applyChoice(doc, {setItem() { throw new Error('blocked'); }}, 'accent', '02');
    assert.strictEqual(doc.attrs.accent, '02');
});
