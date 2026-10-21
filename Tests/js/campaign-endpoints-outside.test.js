// How the circles of the nodes of the campaign builder are moved out (Resources/js/campaign-endpoints-outside.js). The real
// position is checked in the browser (scripts/5/e2e/tests/campaign-endpoints.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const out    = require('../../Resources/js/campaign-endpoints-outside.js');

// core's definitions: [x, y, directionX, directionY, offsetX, offsetY]
const core = () => ({
    top:            {anchors: [0.5, 0, 0, -1, 0, 0], isTarget: true},
    bottom:         {anchors: [0.5, 1, 0, 1, 0, 0]},
    yes:            {anchors: [0, 1, 0, 1, 30, 0]},
    no:             {anchors: [1, 1, 0, 1, -30, 0]},
    leadSource:     {anchors: [0.5, 1, 0, 1, 0, 0]},
    leadSourceLeft: {anchors: [0, 0.5, -1, 0, -1, 0]},
    leadSourceRight: {anchors: [1, 0.5, 1, 0, 1, 0]},
});

test('the top circle goes up, the bottom ones go down, the side ones go to their side', () => {
    const d = core();
    out.shiftDefinitions(d, 4);
    assert.strictEqual(d.top.anchors[5], -4);
    assert.strictEqual(d.bottom.anchors[5], 4);
    assert.strictEqual(d.yes.anchors[5], 4);
    assert.strictEqual(d.no.anchors[5], 4);
    assert.strictEqual(d.leadSource.anchors[5], 4);
    assert.strictEqual(d.leadSourceLeft.anchors[4], -5);
    assert.strictEqual(d.leadSourceRight.anchors[4], 5);
});

test('the other offset and the rest of the definition are left as they are', () => {
    const d = core();
    out.shiftDefinitions(d, 4);
    assert.strictEqual(d.yes.anchors[4], 30);
    assert.strictEqual(d.no.anchors[4], -30);
    assert.deepStrictEqual(d.top.anchors.slice(0, 5), [0.5, 0, 0, -1, 0]);
    assert.strictEqual(d.top.isTarget, true);
});

test('shiftAnchor does not change the anchor it is given', () => {
    const anchor = [0.5, 0, 0, -1, 0, 0];
    out.shiftAnchor(anchor, 4);
    assert.deepStrictEqual(anchor, [0.5, 0, 0, -1, 0, 0]);
});

test('a definition without anchors is skipped', () => {
    const d = {odd: {isTarget: true}};
    out.shiftDefinitions(d, 4);
    assert.deepStrictEqual(d, {odd: {isTarget: true}});
});
