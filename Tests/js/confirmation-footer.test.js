// The wrap of core's Mautic.showConfirmation (Resources/js/confirmation-footer.js) with a small fake of jQuery. How the buttons end up in
// the footer is checked in the browser (scripts/5/e2e/tests/confirmation-footer.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/confirmation-footer.js');

// a jQuery that knows only `.confirmation-modal`: `count` modals, an empty content so moveToFooter leaves them alone
function fake(count, seen) {
    const empty = {length: 0, children: () => ({length: 0})};
    const modal = {find: () => ({first: () => empty})};
    return (selector) => {
        if (selector === '.confirmation-modal') { return {length: count, last: () => { seen.push('last'); return modal; }}; }
        return selector === modal ? modal : {};
    };
}

test('wrap: core runs first with its arguments and its result is returned; the last modal is the one handled', () => {
    const calls = [], seen = [];
    const Mautic = {showConfirmation(el) { calls.push(el); return 'core'; }};

    assert.strictEqual(api.wrap(fake(1, seen), Mautic), true);
    assert.strictEqual(Mautic.showConfirmation('button'), 'core');
    assert.deepStrictEqual(calls, ['button']);
    assert.deepStrictEqual(seen, ['last']);
});

test('wrap: nothing is handled when core opened no modal (a precheck refused)', () => {
    const seen = [];
    const Mautic = {showConfirmation() {}};
    api.wrap(fake(0, seen), Mautic);
    Mautic.showConfirmation();
    assert.deepStrictEqual(seen, []);
});

test('wrap: a second wrap does not wrap again, and a missing core function is not wrapped', () => {
    const Mautic = {showConfirmation() {}};
    assert.strictEqual(api.wrap(fake(0, []), Mautic), true);
    const once = Mautic.showConfirmation;
    assert.strictEqual(api.wrap(fake(0, []), Mautic), false);
    assert.strictEqual(Mautic.showConfirmation, once);
    assert.strictEqual(api.wrap(fake(0, []), {}), false);
});
