// Runs the REAL script that CampaignPendingClickSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the click handler on the document, the stat spans of a campaign node (the check with the done count, the
// hourglass with the pending count: each opens its own list) and Mautic.ajaxifyModal as a recorder.
//
// Run inside the Mautic container, no extra dependencies (Node's built-in runner):
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD   = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/CampaignPendingClickSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignPendingClickSubscriber::script('/s/campaign-pending/', 'Contatos aguardando', '/s/campaign-done/', 'Contatos concluídos');`,
], {encoding: 'utf8'});

// A stat span of a node: `icon` is the icon it holds, `eventId` the data-event-id of the node it sits in (null: in no node).
function stat(icon, eventId = '41') {
    return {
        querySelector: (selector) => (selector === '.' + icon ? {} : null),
        closest: (selector) => {
            assert.strictEqual(selector, '[data-event-id]');

            return eventId === null ? null : {getAttribute: (name) => (name === 'data-event-id' ? eventId : null)};
        },
    };
}

function load() {
    const calls = {modal: []};
    let clickHandler;
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.strictEqual(capture, true); clickHandler = fn; },
        createElement: () => ({attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }}),
    };
    const window  = {};
    const Mautic  = {ajaxifyModal: (el) => calls.modal.push(el.attrs), processPageContent: (r) => { calls.processed = r; }};
    const mQuery  = Object.assign((selector) => ({data: (key) => (selector === '#patches-pending-container' && key === 'target-url' ? calls.container : undefined)}),
        {get: (url, cb) => { calls.get = url; calls.getCb = cb; }});

    vm.runInNewContext(SCRIPT, {window, document, Mautic, mQuery});

    function click(span, extra = {}) {
        const event = {
            button: 0, ...extra, prevented: false, stopped: false,
            preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; },
            target: {closest: (selector) => {
                assert.strictEqual(selector, '.campaign-event-stat > span[data-toggle]');

                return span;
            }},
        };
        clickHandler(event);

        return event;
    }

    return {calls, click, window, Mautic};
}

test('a click on the hourglass opens the pending modal of that node with the route, the target and the title', () => {
    const t = load();

    const event = t.click(stat('ri-hourglass-line', '41'));

    assert.strictEqual(event.prevented, true);
    assert.strictEqual(event.stopped, true);
    assert.deepStrictEqual(t.calls.modal, [{
        href: '/s/campaign-pending/41', 'data-toggle': 'ajaxmodal', 'data-target': '#MauticSharedModal', 'data-header': 'Contatos aguardando',
    }]);
});

test('a click on the check opens the done modal of that node with its own route and title', () => {
    const t = load();

    const event = t.click(stat('ri-check-line', '41'));

    assert.strictEqual(event.prevented, true);
    assert.strictEqual(event.stopped, true);
    assert.deepStrictEqual(t.calls.modal, [{
        href: '/s/campaign-done/41', 'data-toggle': 'ajaxmodal', 'data-target': '#MauticSharedModal', 'data-header': 'Contatos concluídos',
    }]);
});

test('a stat span with another icon is not touched', () => {
    const t = load();

    const event = t.click(stat('ri-mail-line'));

    assert.strictEqual(event.prevented, false);
    assert.strictEqual(event.stopped, false);
    assert.deepStrictEqual(t.calls.modal, []);
});

test('a click that is not on a stat span is not touched', () => {
    const t = load();

    assert.strictEqual(t.click(null).prevented, false);
    assert.deepStrictEqual(t.calls.modal, []);
});

test('an hourglass or a check outside a node (no event id) is not touched', () => {
    const t = load();

    assert.strictEqual(t.click(stat('ri-hourglass-line', null)).prevented, false);
    assert.strictEqual(t.click(stat('ri-check-line', null)).prevented, false);
    assert.deepStrictEqual(t.calls.modal, []);
});

test('the middle button is left to the browser', () => {
    const t = load();

    assert.strictEqual(t.click(stat('ri-hourglass-line'), {button: 1}).prevented, false);
    assert.deepStrictEqual(t.calls.modal, []);
});

test('the handler is added once, however many pages inject the script', () => {
    const t = load();
    let added = 0;
    const original = t.window;

    assert.strictEqual(original.mauticPatchesCampaignPendingClicks, true);
    // a second injection into the same window does nothing
    const document = {addEventListener: () => { added += 1; }, createElement: () => ({})};
    vm.runInNewContext(SCRIPT, {window: original, document, Mautic: {}});

    assert.strictEqual(added, 0);
});

test('when the modal body arrives, its table is loaded into the container through core\'s own loader', () => {
    const t = load();
    t.calls.container = '/s/campaign-pending/41/contacts/1';

    t.Mautic.campaignPendingOnLoad();
    t.calls.getCb({html: 'x'});

    assert.strictEqual(t.calls.get, '/s/campaign-pending/41/contacts/1');
    assert.strictEqual(t.calls.processed.target, '#patches-pending-container');
});

test('a body without the container loads nothing', () => {
    const t = load();

    t.Mautic.campaignPendingOnLoad();

    assert.strictEqual(t.calls.get, undefined);
});
