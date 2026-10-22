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

// The date filters of the segment form also take relative values. Core keeps a filter relative only when it is one of its own keywords
// translated into the language of the user (or starts with a sign); any other text it turns into a fixed date (a field of date and
// time) or ignores. The page gives a map of every word it accepts (the language of the user and English, in lower case) to the
// word core keeps relative, and a field is checked as a whole when it loses focus: a valid value stays (English words are swapped for
// the word of the user's language), any other is cleared.
const WORDS = {hoje: 'hoje', 'amanhã': 'amanhã', 'ontém': 'ontém', 'esta semana': 'esta semana', today: 'hoje', tomorrow: 'amanhã', yesterday: 'ontém', 'this week': 'esta semana'};

test('isFreeText: a text that starts with a letter or a sign is a relative value being typed, not a date', () => {
    for (const text of ['h', 'hoje', 'Today', '-', '-1 days', '+2', 'ã', 'esta semana']) { assert.strictEqual(api.isFreeText(text), true, text); }
    for (const text of ['', '1', '2026-10-21', '14:30', ' ']) { assert.strictEqual(api.isFreeText(text), false, text); }
});

test('isRelative: the words of the page, in the language of the user and in English, in any case', () => {
    for (const text of ['hoje', 'ontém', 'esta semana', 'today', 'yesterday', 'tomorrow', 'this week', 'Today', 'HOJE', 'This Week']) {
        assert.strictEqual(api.isRelative(text, WORDS), true, text);
    }
    for (const text of ['hoj', 'ontem', 'esta', 'hoje ', 'last day', 'now', 'abc']) { assert.strictEqual(api.isRelative(text, WORDS), false, text); }
});

test('isRelative: a sign, a number and a unit', () => {
    for (const text of ['-1 days', '+2 weeks', '-3 months', '-1 day', '+1 year', '-30 minutes', '-2 hours', '-1days', '+10 Days']) {
        assert.strictEqual(api.isRelative(text, WORDS), true, text);
    }
    for (const text of ['-', '-1', '-1 ', '-1 d', '-1 foo', '1 days', '- 1 days', '-1 days ago', '+days', '--1 days']) {
        assert.strictEqual(api.isRelative(text, WORDS), false, text);
    }
});

test('isRelative: without the map of the page only the signed offsets are valid', () => {
    assert.strictEqual(api.isRelative('hoje', undefined), false);
    assert.strictEqual(api.isRelative('-1 days', undefined), true);
});

test('relativeValue: what the field keeps when it loses focus', () => {
    assert.strictEqual(api.relativeValue('yesterday', WORDS), 'ontém', 'English is swapped for the word of the user');
    assert.strictEqual(api.relativeValue('This Week', WORDS), 'esta semana');
    assert.strictEqual(api.relativeValue('TODAY', WORDS), 'hoje');
    assert.strictEqual(api.relativeValue('hoje', WORDS), 'hoje');
    assert.strictEqual(api.relativeValue('  ontém ', WORDS), 'ontém', 'blanks around it are dropped');
    assert.strictEqual(api.relativeValue('-1 days', WORDS), '-1 days');
    assert.strictEqual(api.relativeValue('ontem', WORDS), '', 'not a word of core: cleared');
    assert.strictEqual(api.relativeValue('abc', WORDS), '');
    assert.strictEqual(api.relativeValue('-1', WORDS), '');
});
