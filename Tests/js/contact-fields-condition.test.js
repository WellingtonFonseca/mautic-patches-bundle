// The decisions of the rows of the campaign condition "Contact fields" (Resources/js/contact-fields-condition.js): which
// value control a field and an operator get, which operator stays when the field changes, how A|B|C is read. The DOM part
// is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const rows   = require('../../Resources/js/contact-fields-condition.js');

const countries = {BR: 'Brasil', PT: 'Portugal'};

test('controlFor: a text field gets a text input, even for including / excluding (the chips take it)', () => {
    assert.strictEqual(rows.controlFor({}, '='), 'text');
    assert.strictEqual(rows.controlFor({}, 'in'), 'text');
    assert.strictEqual(rows.controlFor(null, '!in'), 'text');
});

test('controlFor: a list field gets a select, a multiple select for including / excluding', () => {
    assert.strictEqual(rows.controlFor(countries, '='), 'select');
    assert.strictEqual(rows.controlFor(countries, '!='), 'select');
    assert.strictEqual(rows.controlFor(countries, 'in'), 'multi');
    assert.strictEqual(rows.controlFor(countries, '!in'), 'multi');
});

test('controlFor: a pattern (regexp, like) is typed, and empty / not empty have no value', () => {
    assert.strictEqual(rows.controlFor(countries, 'regexp'), 'text');
    assert.strictEqual(rows.controlFor(countries, 'like'), 'text');
    assert.strictEqual(rows.controlFor(countries, 'empty'), 'disabled');
    assert.strictEqual(rows.controlFor({}, '!empty'), 'disabled');
});

test('keepOperator: the operator stays when the new field offers it, else the first one', () => {
    assert.strictEqual(rows.keepOperator({'=': 'is', 'in': 'including'}, 'in'), 'in');
    assert.strictEqual(rows.keepOperator({'=': 'is', 'gt': 'greater'}, 'in'), '=');
    assert.strictEqual(rows.keepOperator({}, 'in'), '');
});

test('split: the stored A|B|C becomes values; blanks are dropped', () => {
    assert.deepStrictEqual(rows.split('BR|PT'), ['BR', 'PT']);
    assert.deepStrictEqual(rows.split(' BR || PT | '), ['BR', 'PT']);
    assert.deepStrictEqual(rows.split(null), []);
    assert.deepStrictEqual(rows.split(''), []);
});
