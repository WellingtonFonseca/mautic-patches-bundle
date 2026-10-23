// The logic of the plugin tile status (Resources/js/plugin-tile-status.js): the wrap of core's Mautic.integrationConfigOnLoad and what an
// answer paints, with a small fake of jQuery. The page is checked in the browser (scripts/5/e2e/tests/plugin-tile-status.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/plugin-tile-status.js');

function fake() {
    const calls = [];
    const $ = (selector) => ({toggleClass: (name, on) => { calls.push([selector, name, on]); return this; }});

    return {$, calls};
}

test('paint: enabled takes integration-disabled off the tile of the plugin, disabled puts it on (0 / 1 or false / true)', () => {
    const {$, calls} = fake();

    assert.strictEqual(api.paint($, {name: 'N8nDispatch', enabled: 1}), true);
    assert.strictEqual(api.paint($, {name: 'N8nDispatch', enabled: 0}), true);
    assert.strictEqual(api.paint($, {name: 'Twilio', enabled: true}), true);
    assert.strictEqual(api.paint($, {name: 'Twilio', enabled: false}), true);

    assert.deepStrictEqual(calls, [
        ['.integration-N8nDispatch', 'integration-disabled', false],
        ['.integration-N8nDispatch', 'integration-disabled', true],
        ['.integration-Twilio', 'integration-disabled', false],
        ['.integration-Twilio', 'integration-disabled', true],
    ]);
});

test('paint: an answer without a plugin name or without enabled (the form again, the list) changes nothing', () => {
    const {$, calls} = fake();

    assert.strictEqual(api.paint($, undefined), false);
    assert.strictEqual(api.paint($, {}), false);
    assert.strictEqual(api.paint($, {name: 'N8nDispatch'}), false);
    assert.strictEqual(api.paint($, {enabled: 1}), false);
    assert.deepStrictEqual(calls, []);
});

test('wrap: core\'s function still runs, with its arguments and its result, and then the tile is painted; wrapping twice wraps once', () => {
    const {$, calls} = fake();
    const seen = [];
    const Mautic = {integrationConfigOnLoad: function (container, response) { seen.push([container, response]); return 'core result'; }};
    const response = {name: 'Twilio', enabled: 1};

    assert.strictEqual(api.wrap($, Mautic), true);
    assert.strictEqual(api.wrap($, Mautic), true);
    assert.strictEqual(Mautic.integrationConfigOnLoad('#modal', response), 'core result');

    assert.deepStrictEqual(seen, [['#modal', response]], 'core ran once');
    assert.deepStrictEqual(calls, [['.integration-Twilio', 'integration-disabled', false]], 'painted once');
});

test('wrap: false while core\'s function is not there yet', () => {
    const {$} = fake();

    assert.strictEqual(api.wrap($, undefined), false);
    assert.strictEqual(api.wrap($, {}), false);
});
