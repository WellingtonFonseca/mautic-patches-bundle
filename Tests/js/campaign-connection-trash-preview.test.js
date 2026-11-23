// Test-tags: areas=campaign risks=FLUXO,UI
// The Preview tab of the campaign page must not let a line be unlinked (Resources/js/campaign-connection-trash.js, lockPreview),
// while the builder keeps its unlink button. Runs the REAL script against a fake document and a fake jsPlumb instance; the real
// drag in the browser is checked in scripts/5/e2e/tests/campaign-connection-trash.spec.js.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const fs     = require('node:fs');

const SOURCE = fs.readFileSync(path.resolve(__dirname, '../../Resources/js/campaign-connection-trash.js'), 'utf8');

function connection() {
    const c = {detachable: true, endpoints: [{enabled: true}, {enabled: true}]};
    c.setDetachable = (v) => { c.detachable = v; };
    c.endpoints.forEach((e) => { e.setEnabled = (v) => { e.enabled = v; }; });
    return c;
}

function instance(inPreview, connections) {
    const handlers = {};
    return {
        handlers,
        getContainer: () => ({closest: (sel) => (inPreview && sel === '#preview-container' ? {} : null)}),
        getAllConnections: () => connections,
        bind: (name, fn) => { (handlers[name] = handlers[name] || []).push(fn); },
        deleteConnection: () => { throw new Error('deleteConnection must not be called'); },
    };
}

// loads the script on a fresh fake window; returns the Mautic object after install
function load(inst) {
    const styles = [];
    const document = {
        createElement: () => ({style: {}}),
        head: {appendChild: (s) => styles.push(s.textContent)},
        addEventListener() {},
    };
    const root = {document};
    const Mautic = {campaignBuilderInstance: inst, onPageLoad() {}, prepareCampaignCanvas() {}};
    vm.runInNewContext(SOURCE, root, {filename: 'campaign-connection-trash.js'});
    root.mauticPatchesConnectionTrashApi.install(Mautic, {title: 'Desconectar'});
    return {Mautic, styles, root};
}

test('the Preview: every line is made not detachable and every circle disabled', () => {
    const lines = [connection(), connection()];
    load(instance(true, lines));
    lines.forEach((l) => {
        assert.strictEqual(l.detachable, false);
        assert.ok(l.endpoints.every((e) => e.enabled === false));
    });
});

test('the Preview: a line made later is locked, and detach and drop are refused', () => {
    const inst = instance(true, []);
    load(inst);
    assert.strictEqual(inst.handlers.beforeDetach[0](), false);
    assert.strictEqual(inst.handlers.beforeDrop[0](), false);
    const late = connection();
    inst.handlers.connection[0]({connection: late});
    assert.strictEqual(late.detachable, false);
    assert.ok(late.endpoints.every((e) => e.enabled === false));
});

test('the Preview: no unlink button is bound (no deleteConnection, no connectionDetached handler)', () => {
    const inst = instance(true, [connection()]);
    load(inst);
    assert.strictEqual(inst.handlers.connectionDetached, undefined);
});

test('the Preview: locking twice binds the handlers once', () => {
    const inst = instance(true, [connection()]);
    const {Mautic} = load(inst);
    Mautic.onPageLoad();
    assert.strictEqual(inst.handlers.beforeDetach.length, 1);
    assert.strictEqual(inst.handlers.beforeDrop.length, 1);
});

test('the Preview circles are shown again, only inside #preview-container', () => {
    const {styles} = load(instance(true, []));
    const css = styles.join('');
    assert.match(css, /#preview-container \.jtk-endpoint svg\{display:block!important\}/);
    assert.doesNotMatch(css, /(^|[,}])\s*\.jtk-endpoint svg\{display:block/);
});

test('the builder is not locked: it keeps its lines detachable', () => {
    const line = connection();
    const inst = instance(false, [line]);
    // the builder decorates each line with a button, which needs a real DOM: only the lock is checked here
    try { load(inst); } catch (e) { /* decorate needs a DOM the fake does not have */ }
    assert.strictEqual(line.detachable, true);
    assert.ok(line.endpoints.every((e) => e.enabled === true));
    assert.strictEqual(inst.handlers.beforeDetach, undefined);
    assert.strictEqual(inst.handlers.beforeDrop, undefined);
});
