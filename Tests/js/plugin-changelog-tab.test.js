// What Resources/js/plugin-changelog-tab.js exposes: the install of the callback core calls with the block. The tab itself (moving the
// block into a pane of the modal) is checked in the browser (scripts/5/e2e/tests/plugin-changelog.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/plugin-changelog-tab.js');

// a jQuery whose every element is empty (no tab content, no tab list): every way up the page ends in nothing
const empty = () => { const node = {length: 0, closest: () => node, prev: () => node}; return node; };

test('install: the callback of the template is on Mautic and hands the block to moveToTab', () => {
    const Mautic = {};
    api.install(empty, Mautic);

    assert.strictEqual(typeof Mautic.patchesChangelogTab, 'function');
    assert.doesNotThrow(() => Mautic.patchesChangelogTab({}));
});

test('install: without jQuery or Mautic nothing is touched', () => {
    assert.doesNotThrow(() => api.install(null, {}));
    assert.doesNotThrow(() => api.install(() => ({}), null));
});

test('moveToTab: a block outside a tab content is left alone', () => {
    assert.strictEqual(api.moveToTab(empty, {}), false);
});
