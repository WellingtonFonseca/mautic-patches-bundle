// Runs the REAL script that CampaignBuilderWaitLabelSubscriber puts on every admin page (it replaces core's
// Mautic.campaignBuilderUpdateLabel) against a fake jsPlumb connection whose getOverlays() returns an object keyed by id,
// like jsPlumb 2.15.6 does.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script() {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignBuilderWaitLabelSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignBuilderWaitLabelSubscriber::script();`,
    ], {encoding: 'utf8'});
}

// A connection like jsPlumb 2.15.6: overlays kept in an object by id; removeOverlay deletes the entry.
function connection(overlays) {
    const conn = {
        sourceId: 'src', targetId: 'CampaignEvent_1', overlays: {...overlays},
        getOverlays() { return this.overlays; },
        removeOverlay(id) { delete this.overlays[id]; },
        addOverlay([type, opts]) { this.overlays[opts.id] = {type, id: opts.id, label: opts.label}; },
    };

    return conn;
}

function load(conn, labels) {
    const Mautic = {
        campaignBuilderLabels: labels,
        campaignBuilderInstance: {select: () => ({each: (fn) => [conn].forEach(fn)})},
        campaignBuilderUpdateLabel() { throw new Error('core version must have been replaced'); },
        onPageLoad() { return 'core'; },
    };
    const ready = [];
    vm.runInNewContext(script(), {Mautic, window: {}, mQuery: (fn) => ready.push(fn)});
    ready.forEach((fn) => fn());

    return Mautic;
}

test('the old label is removed and the new one drawn: one label per connection', () => {
    const conn = connection({
        'src_CampaignEvent_1_connectionLabel': {type: 'Label', id: 'src_CampaignEvent_1_connectionLabel', label: 'wait 2 days'},
    });
    const Mautic = load(conn, {CampaignEvent_1: 'wait 3 days'});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    const labels = Object.values(conn.overlays).filter((o) => o.type === 'Label');
    assert.strictEqual(labels.length, 1);
    assert.strictEqual(labels[0].label, 'wait 3 days');
});

test('an empty label removes the old one and draws nothing', () => {
    const conn = connection({a: {type: 'Label', id: 'a', label: 'wait 2 days'}, b: {type: 'Arrow', id: 'b'}});
    const Mautic = load(conn, {});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.deepStrictEqual(Object.keys(conn.overlays), ['b']);
});

test('overlays given as an array are handled too', () => {
    const conn = connection({});
    conn.getOverlays = () => [{type: 'Label', id: 'a'}];
    let removed = null;
    conn.removeOverlay = (id) => { removed = id; };
    const Mautic = load(conn, {});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(removed, 'a');
});

test('the page load of core still runs, and the patch holds after it', () => {
    const conn = connection({});
    const Mautic = load(conn, {});

    assert.strictEqual(Mautic.onPageLoad(), 'core');
    assert.doesNotThrow(() => Mautic.campaignBuilderUpdateLabel('CampaignEvent_1'));
});
