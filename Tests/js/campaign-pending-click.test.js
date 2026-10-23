// Runs the REAL script that CampaignPendingClickSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the click handler on the document, the stat spans of a campaign node (the hourglass with the pending count
// opens the list; the check with the done count does nothing) and Mautic.ajaxifyModal as a recorder.
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
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignPendingClickSubscriber::script('/s/campaign-pending/', 'Contatos aguardando');`,
], {encoding: 'utf8'});

// A DOM element as much as the script uses it: children, text, a class, a click handler. Setting innerHTML to '' empties it.
function element() {
    const classes = new Set();
    const el = {attrs: {}, children: [], textContent: '', className: '', hidden: false, disabled: false, onclick: null,
        classList: {add: (c) => classes.add(c), remove: (c) => classes.delete(c), has: (c) => classes.has(c)},
        setAttribute(k, v) { this.attrs[k] = v; }, appendChild(child) { this.children.push(child); return child; }};
    Object.defineProperty(el, 'innerHTML', {get: () => '', set: () => { el.children = []; }});

    return el;
}

// The "Recalculate dates" box of a Wait node: its data attributes (texts already translated) and the elements the script finds in it: the
// message and the three buttons (Apply and Cancel start hidden).
function recalcBox() {
    const attrs = {
        'data-simulate-url': '/s/campaign-wait/41/recalculate/simulate', 'data-apply-url': '/s/campaign-wait/41/recalculate/apply',
        'data-text-intro': 'Com a regra atual:', 'data-text-changed': '%count% mudam', 'data-text-leaving_now': '%count% saem agora',
        'data-text-unchanged': '%count% ficam', 'data-text-nothing': 'Nada a alterar.', 'data-text-applied': '%count% recalculadas',
    };
    const out = element(); const running = element(); const start = element(); const apply = element(); const cancel = element();
    out.hidden = true; running.hidden = true; apply.hidden = true; cancel.hidden = true;
    const found = {'.patches-wait-recalc-result': out, '.patches-wait-recalc-running': running, '[data-recalc=start]': start, '[data-recalc=apply]': apply, '[data-recalc=cancel]': cancel};
    const box = element();
    box.getAttribute = (name) => attrs[name];
    box.querySelector = (selector) => found[selector] || null;

    return Object.assign(box, {out, running, start, apply, cancel});
}

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
        createElement: () => element(),
        getElementById: (id) => (id === 'patches-wait-recalc' ? calls.box || null : null),
    };
    const window  = {};
    const Mautic  = {ajaxifyModal: (el) => calls.modal.push(el.attrs), processPageContent: (r) => { calls.processed = r; }, processAjaxError: (...a) => { calls.error = a; }};
    const mQuery  = Object.assign((selector) => ({data: (key) => (selector === '#patches-pending-container' && key === 'target-url' ? calls.container : undefined)}),
        {
            get: (url, cb) => { calls.get = url; calls.getCb = cb; return {fail: (fn) => { calls.failCb = fn; }}; },
            getJSON: (url, cb) => { calls.json = url; calls.jsonCb = cb; return {fail: (fn) => { calls.jsonFail = fn; }}; },
            ajax: (options) => { calls.ajax = options; const chain = {done: (fn) => { calls.ajaxDone = fn; return chain; }, fail: (fn) => { calls.ajaxFail = fn; return chain; }}; return chain; },
        });

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

test('the check of the done count is not touched: its list would mix who finished the node at another moment with who waits now', () => {
    const t = load();

    const event = t.click(stat('ri-check-line', '41'));

    assert.strictEqual(event.prevented, false);
    assert.strictEqual(event.stopped, false);
    assert.deepStrictEqual(t.calls.modal, []);
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

test('an hourglass outside a node (no event id) is not touched', () => {
    const t = load();

    assert.strictEqual(t.click(stat('ri-hourglass-line', null)).prevented, false);
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

test('a table request that fails reports the error through core instead of leaving the spinner', () => {
    const t = load();
    t.calls.container = '/s/campaign-pending/41/contacts/1';

    t.Mautic.campaignPendingOnLoad();
    t.calls.failCb({status: 403}, 'error', 'Forbidden');

    assert.deepStrictEqual(t.calls.error, [{status: 403}, 'error', 'Forbidden']);
});

test('a body without the container loads nothing', () => {
    const t = load();

    t.Mautic.campaignPendingOnLoad();

    assert.strictEqual(t.calls.get, undefined);
});

const messages = (el) => el.children.map((child) => child.textContent || child.children.map((li) => li.textContent).join('|'));
// which of the three buttons can be seen
const visible = (box) => ['start', 'apply', 'cancel'].filter((name) => !box[name].hidden);

test('without the recalculate box (any other node) nothing else is set up', () => {
    const t = load();
    t.calls.container = '/s/campaign-pending/41/contacts/1';

    t.Mautic.campaignPendingOnLoad();

    assert.strictEqual(t.calls.json, undefined);
});

test('at rest only Recalculate is shown, with the message hidden', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.Mautic.campaignPendingOnLoad();

    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.box.out.hidden, true);
});

test('Recalculate asks for the simulation, shows "Recalculating..." while it runs and then leaves only Apply and Cancel, under the message', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.Mautic.campaignPendingOnLoad();

    t.calls.box.start.onclick();
    assert.strictEqual(t.calls.json, '/s/campaign-wait/41/recalculate/simulate');
    assert.strictEqual(t.calls.box.start.disabled, true, 'the button waits for the answer');
    assert.strictEqual(t.calls.box.running.hidden, false, 'the line "<spinner> Recalculating..." is shown');
    assert.strictEqual(t.calls.box.out.hidden, true, 'in place of the message');
    t.calls.jsonCb({summary: {total: 5, changed: 3, leaving_now: 2, unchanged: 0, without_date: 0}, canApply: true});

    assert.strictEqual(t.calls.box.running.hidden, true, 'the running line gives way to the result');
    assert.strictEqual(t.calls.box.out.hidden, false);
    assert.deepStrictEqual(messages(t.calls.box.out), ['Com a regra atual:', '3 mudam|2 saem agora']);
    assert.deepStrictEqual(visible(t.calls.box), ['apply', 'cancel'], 'Recalculate gives way to Apply and Cancel');
});

test('Cancel goes back: the message hidden and Recalculate alone, nothing written', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.Mautic.campaignPendingOnLoad();
    t.calls.box.start.onclick();
    t.calls.jsonCb({summary: {total: 5, changed: 3, leaving_now: 2, unchanged: 0, without_date: 0}, canApply: true});

    t.calls.box.cancel.onclick();

    assert.strictEqual(t.calls.box.out.hidden, true);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.ajax, undefined);
});

test('nothing to change: a sentence and Recalculate stays, no Apply', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.Mautic.campaignPendingOnLoad();

    t.calls.box.start.onclick();
    t.calls.jsonCb({summary: {total: 3, changed: 0, leaving_now: 0, unchanged: 3, without_date: 0}, canApply: false});

    assert.deepStrictEqual(messages(t.calls.box.out), ['Nada a alterar.']);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
});

test('Apply posts to the apply route, says how many were recalculated, reloads the table and goes back to Recalculate', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.calls.container = '/s/campaign-pending/41/contacts/1';
    t.Mautic.campaignPendingOnLoad();
    t.calls.box.start.onclick();
    t.calls.jsonCb({summary: {total: 5, changed: 3, leaving_now: 2, unchanged: 0, without_date: 0}, canApply: true});
    delete t.calls.get;

    t.calls.box.apply.onclick();
    assert.strictEqual(t.calls.ajax.type, 'POST');
    assert.strictEqual(t.calls.ajax.url, '/s/campaign-wait/41/recalculate/apply');
    t.calls.ajaxDone({applied: 5, summary: {}});

    assert.deepStrictEqual(messages(t.calls.box.out), ['5 recalculadas']);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.get, '/s/campaign-pending/41/contacts/1', 'the table shows the new state');
});

test('a failed simulation or application goes through core\'s error handling and the buttons can be used again', () => {
    const t = load();
    t.calls.box = recalcBox();
    t.Mautic.campaignPendingOnLoad();

    t.calls.box.start.onclick();
    t.calls.jsonFail({status: 403}, 'error', 'Forbidden');
    assert.deepStrictEqual(t.calls.error, [{status: 403}, 'error', 'Forbidden']);
    assert.strictEqual(t.calls.box.start.disabled, false, 'the button can be tried again');
    assert.strictEqual(t.calls.box.running.hidden, true);

    t.calls.box.start.onclick();
    t.calls.jsonCb({summary: {total: 1, changed: 1, leaving_now: 0, unchanged: 0, without_date: 0}, canApply: true});
    t.calls.box.apply.onclick();
    t.calls.ajaxFail({status: 500}, 'error', 'Server');
    assert.deepStrictEqual(t.calls.error, [{status: 500}, 'error', 'Server']);
    assert.strictEqual(t.calls.box.apply.disabled, false);
});
