// The logic of the including / excluding chips of the segment form (Resources/js/segment-in-chips.js): splitting what is
// typed or pasted, the value stored (A|B|C), the suggestions and the URL each filter asks them from. The DOM part is
// checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const chips  = require('../../Resources/js/segment-in-chips.js');

test('parse: the stored value A|B|C becomes values, empty parts and repeats are dropped', () => {
    assert.deepStrictEqual(chips.parse('A|B|C'), ['A', 'B', 'C']);
    assert.deepStrictEqual(chips.parse(''), []);
    assert.deepStrictEqual(chips.parse(null), []);
    assert.deepStrictEqual(chips.parse('A||B|A| '), ['A', 'B']);
});

test('join: the values are stored with |', () => {
    assert.strictEqual(chips.join(['A', 'B']), 'A|B');
    assert.strictEqual(chips.join([]), '');
});

test('add: Enter adds what was typed, even a value that is not in the list', () => {
    assert.deepStrictEqual(chips.add(['A'], 'novo valor'), ['A', 'novo valor']);
});

test('add: a pasted column (lines, tabs) becomes several values; a value already there is not repeated', () => {
    assert.deepStrictEqual(chips.add(['A'], 'B\r\nC\nA\n'), ['A', 'B', 'C']);
    assert.deepStrictEqual(chips.add([], 'x\ty'), ['x', 'y']);
});

test('add: the separator typed by hand also splits, spaces around a value are trimmed, inner spaces stay', () => {
    assert.deepStrictEqual(chips.add([], ' São Paulo | Rio de Janeiro '), ['São Paulo', 'Rio de Janeiro']);
});

test('add: comma and semicolon are part of the value (they do not split)', () => {
    assert.deepStrictEqual(chips.add([], 'a,b;c'), ['a,b;c']);
});

test('add: nothing typed adds nothing', () => {
    assert.deepStrictEqual(chips.add(['A'], '  '), ['A']);
});

test('remove: takes one value out by its position', () => {
    assert.deepStrictEqual(chips.remove(['A', 'B', 'C'], 1), ['A', 'C']);
    assert.deepStrictEqual(chips.remove(['A'], 5), ['A']);
});

test('suggestions: hide what is already chosen and keep the order', () => {
    assert.deepStrictEqual(chips.suggestions(['a', 'b', 'c'], ['b']), ['a', 'c']);
});

test('url: a contact or company field asks the bundle route', () => {
    const cfg = {valuesUrl: '/s/segment-filter-values', customUrl: '/s/custom/segment-filter-values.json'};
    assert.strictEqual(chips.url(cfg, 'lead', 'inst_alias', 'ab c'), '/s/segment-filter-values?object=lead&field=inst_alias&q=ab%20c&limit=20');
    assert.strictEqual(chips.url(cfg, 'company', 'companyname', ''), '/s/segment-filter-values?object=company&field=companyname&q=&limit=20');
});

test('url: a Custom Object field asks the Custom Objects route', () => {
    const cfg = {valuesUrl: '/s/segment-filter-values', customUrl: '/s/custom/segment-filter-values.json'};
    assert.strictEqual(chips.url(cfg, 'custom_object', 'cmf_14', 'x'), '/s/custom/segment-filter-values.json?field=cmf_14&q=x&limit=20');
});

test('url: no Custom Objects plugin means no URL (typing still works)', () => {
    assert.strictEqual(chips.url({valuesUrl: '/s/v', customUrl: null}, 'custom_object', 'cmf_14', 'x'), null);
});

test('url: an object that is neither has no suggestions', () => {
    assert.strictEqual(chips.url({valuesUrl: '/s/v', customUrl: null}, 'behaviors', 'x', ''), null);
});

test('wants: only the including / excluding operators on a plain text input', () => {
    assert.strictEqual(chips.wants('in', 'text'), true);
    assert.strictEqual(chips.wants('!in', 'text'), true);
    assert.strictEqual(chips.wants('in', 'select-multiple'), false, 'a list of options is already a multi select');
    assert.strictEqual(chips.wants('=', 'text'), false);
    assert.strictEqual(chips.wants('regexp', 'text'), false);
});
