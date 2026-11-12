// The choice of the Appearance button (Resources/js/appearance-button.js). The button, the modal and the radios are checked in the
// browser (scripts/7/e2e/tests/appearance-modal.spec.js); the save of the preference is AppearanceModalControllerSaveTest.
//
//   docker exec mautic7-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/appearance-button.js');

function fakeDoc(attributes) {
    const attrs = Object.assign({}, attributes);
    return {documentElement: {getAttribute: (n) => (n in attrs ? attrs[n] : null), setAttribute: (n, v) => { attrs[n] = v; }}, attrs};
}

test('iconFor: a moon on the light themes, a sun on the three dark ones', () => {
    for (const t of ['light', 'solarized-light', null, 'unknown']) { assert.strictEqual(api.iconFor(t), 'ri-moon-line', String(t)); }
    for (const t of ['dark', 'solarized-dark', 'freire']) { assert.strictEqual(api.iconFor(t), 'ri-sun-line', t); }
});

test('currentChoice: the attribute of <html> (rendered from the preference), then the default', () => {
    assert.strictEqual(api.currentChoice(fakeDoc({theme: 'freire'}), 'theme'), 'freire');
    assert.strictEqual(api.currentChoice(fakeDoc({}), 'theme'), 'light');
});

test('choose: sets the attribute at once and posts the theme to the save url', () => {
    const doc = fakeDoc({theme: 'light'}), posts = [];
    const $ = {post: (url, data) => posts.push([url, data])};

    api.choose($, doc, {saveUrl: '/s/appearance-modal/save'}, 'theme', 'dark');

    assert.strictEqual(doc.attrs.theme, 'dark');
    assert.deepStrictEqual(posts, [['/s/appearance-modal/save', {theme: 'dark'}]]);
});

test('install: a second install on the same page adds no second set of handlers', () => {
    let handlers = 0;
    const $ = () => ({on() { handlers++; }});
    const doc = {readyState: 'loading', addEventListener() {}};

    api.install($, {}, doc, {url: '/x', saveUrl: '/y', label: 'A'});
    const first = handlers;
    api.install($, {}, doc, {url: '/x', saveUrl: '/y', label: 'A'});

    assert.strictEqual(first, 2);
    assert.strictEqual(handlers, first);
});
