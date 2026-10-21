// The formatter of the date / time fields (Resources/js/time-input-format.js): what is typed becomes the picker's format,
// slots held to their range, a partial time completed on blur. The listeners on the document are checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/time-input-format.js');

test('time: digits get the colon, which shows as soon as the hour is full', () => {
    assert.strictEqual(api.format('1', 'H:i'), '1');
    assert.strictEqual(api.format('14', 'H:i'), '14:');
    assert.strictEqual(api.format('143', 'H:i'), '14:3');
    assert.strictEqual(api.format('1430', 'H:i'), '14:30');
    assert.strictEqual(api.format('14:30', 'H:i'), '14:30');
    assert.strictEqual(api.format('143099', 'H:i'), '14:30', 'extra digits are dropped');
});

test('time: letters are dropped, and a typed colon is not doubled', () => {
    assert.strictEqual(api.format('1a4', 'H:i'), '14:');
    assert.strictEqual(api.format('14::', 'H:i'), '14:');
    assert.strictEqual(api.format('abc', 'H:i'), '');
    assert.strictEqual(api.format('', 'H:i'), '');
});

test('time: deleting does not bring the colon back, so Backspace can clear the field', () => {
    assert.strictEqual(api.format('14:', 'H:i', true), '14');
    assert.strictEqual(api.format('14', 'H:i', true), '14');
});

test('time: slots are held to their range', () => {
    assert.strictEqual(api.format('9', 'H:i'), '09:', 'a first digit above 2 cannot start an hour');
    assert.strictEqual(api.format('99', 'H:i'), '09:09', 'the second 9 starts the minutes, which also get a 0');
    assert.strictEqual(api.format('25', 'H:i'), '23:', 'a full hour above 23 is lowered to 23');
    assert.strictEqual(api.format('1475', 'H:i'), '14:07');
    assert.strictEqual(api.format('1459', 'H:i'), '14:59');
    assert.strictEqual(api.format('146', 'H:i'), '14:06', 'a first minute digit above 5 gets a 0');
});

test('date and datetime follow the picker format', () => {
    assert.strictEqual(api.format('20261021', 'Y-m-d'), '2026-10-21');
    assert.strictEqual(api.format('202610211430', 'Y-m-d H:i'), '2026-10-21 14:30');
    assert.strictEqual(api.format('2026', 'Y-m-d'), '2026-');
    assert.strictEqual(api.format('20261', 'Y-m-d'), '2026-1');
    assert.strictEqual(api.format('202613', 'Y-m-d'), '2026-12-', 'month above 12 is lowered');
    assert.strictEqual(api.format('20260', 'Y-m-d'), '2026-0');
    assert.strictEqual(api.format('202602', 'Y-m-d'), '2026-02-');
    assert.strictEqual(api.format('2026023', 'Y-m-d'), '2026-02-3');
    assert.strictEqual(api.format('20260232', 'Y-m-d'), '2026-02-31');
});

test('a format with a token that is not known is left alone', () => {
    assert.strictEqual(api.tokens('h:i A'), null);
    assert.strictEqual(api.tokens('F j, Y'), null);
    assert.strictEqual(api.format('abc', 'h:i A'), 'abc');
    assert.strictEqual(api.tokens(''), null);
    assert.strictEqual(api.tokens(undefined), null);
});

test('complete: a partial time is filled on blur', () => {
    assert.strictEqual(api.complete('9', 'H:i'), '09:00');
    assert.strictEqual(api.complete('14:', 'H:i'), '14:00');
    assert.strictEqual(api.complete('14:3', 'H:i'), '14:03');
    assert.strictEqual(api.complete('14:30', 'H:i'), '14:30');
    assert.strictEqual(api.complete('', 'H:i'), '');
    assert.strictEqual(api.complete('2026-10-21 9', 'Y-m-d H:i'), '2026-10-21 09:00');
});

test('complete: a partial date is not touched', () => {
    assert.strictEqual(api.complete('2026-1', 'Y-m-d'), '2026-1');
    assert.strictEqual(api.complete('2026-10-21', 'Y-m-d'), '2026-10-21');
});
