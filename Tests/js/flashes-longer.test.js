// The logic of the notifications that stay longer (Resources/js/flashes-longer.js): how long each kind stays, and the wrap of core's
// Mautic.setFlashes with a small fake of jQuery. The fading is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/flashes-longer.js');

test('duration: a notice stays 10 s, a warning 15 s, an error 20 s (both the growl and the alert classes)', () => {
    assert.strictEqual(api.duration('alert alert-growl alert-new'), 10000);
    assert.strictEqual(api.duration('alert alert-growl alert-growl--warning'), 15000);
    assert.strictEqual(api.duration('alert alert-growl alert-growl--error'), 20000);
    assert.strictEqual(api.duration('alert alert-success'), 10000);
    assert.strictEqual(api.duration('alert alert-warning'), 15000);
    assert.strictEqual(api.duration('alert alert-danger'), 20000);
    assert.ok(api.STAY.error > 4000 && api.STAY.notice > 4000, 'longer than core\'s 4 seconds');
});

// a jQuery that knows #flashes: the alerts in it, and a hook for what a `.each` sees
function fake(alerts) {
    const $ = (arg) => {
        if (arg === '#flashes .alert') { return {toArray: () => alerts.slice(), each: (fn) => alerts.slice().forEach((el) => fn.call(el))}; }
        return {on() { return this; }, fadeTo() { return this; }};
    };

    return $;
}

// the timers are recorded, not run: nothing waits 20 seconds
function withTimers(fn) {
    const realSet = global.setTimeout, realClear = global.clearTimeout;
    const delays = [];
    global.setTimeout = (cb, ms) => { delays.push(ms); return delays.length; };
    global.clearTimeout = () => {};
    try { fn(delays); } finally { global.setTimeout = realSet; global.clearTimeout = realClear; }
}

test('wrap: core is asked not to auto-close, and a new alert is timed once; an old one is not', () => {
    const old = {className: 'alert alert-growl'};
    const added = {className: 'alert alert-growl alert-growl--error'};
    const alerts = [old];
    const calls = [];
    const Mautic = {setFlashes(flashes, autoClose) { calls.push([flashes, autoClose]); alerts.push(added); return 'r'; }};

    assert.strictEqual(api.wrap(fake(alerts), Mautic), true);
    withTimers((delays) => {
        assert.strictEqual(Mautic.setFlashes('<div>', undefined), 'r');
        assert.deepStrictEqual(delays, [20000], 'the error is timed for 20 s, once');
    });
    assert.deepStrictEqual(calls, [['<div>', false]]);
    assert.strictEqual(added.mauticPatchesTimed, true);
    assert.strictEqual(old.mauticPatchesTimed, undefined);
});

test('wrap: a caller that asks for no auto-close is obeyed, and a second wrap changes nothing', () => {
    const added = {className: 'alert alert-growl'};
    const alerts = [];
    const Mautic = {setFlashes() { alerts.push(added); }};

    assert.strictEqual(api.wrap(fake(alerts), Mautic), true);
    withTimers((delays) => {
        Mautic.setFlashes('<div>', false);
        assert.deepStrictEqual(delays, [], 'no timer for a caller that asked for none');
    });
    assert.strictEqual(added.mauticPatchesTimed, undefined);
    const wrapped = Mautic.setFlashes;
    assert.strictEqual(api.wrap(fake(alerts), Mautic), false);
    assert.strictEqual(Mautic.setFlashes, wrapped);
});

test('wrap: nothing to wrap while core has no setFlashes', () => {
    assert.strictEqual(api.wrap(fake([]), {}), false);
    assert.strictEqual(api.wrap(fake([]), undefined), false);
});
