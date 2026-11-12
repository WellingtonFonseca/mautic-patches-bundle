// Runs the REAL script of the "Recalculate dates" box of the second "Ações" tab (taken from CampaignWaitRecalculateScript::SCRIPT, not
// copied) against a small fake browser: the click handler on the document, the badge buttons of the step rows (a row of a Wait step the
// user can edit carries the simulate and apply urls) and the box above the contacts table.
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
const CLASS_FILE = path.resolve(__dirname, '../../Service/CampaignWaitRecalculateScript.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\Service\\CampaignWaitRecalculateScript::SCRIPT;`,
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

// The "Recalculate dates" box above the table: its data attributes (texts already translated) and the elements the script finds in it: the
// message and the three buttons (Apply and Cancel start hidden).
function recalcBox() {
    const attrs = {
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

// The row of a step: `eventId` null is a row without the urls (not a Wait step, or no edit access).
function row(eventId) {
    const attrs = eventId === null ? {} : {
        'data-recalc-simulate-url': `/s/campaign-wait/${eventId}/recalculate/simulate`, 'data-recalc-apply-url': `/s/campaign-wait/${eventId}/recalculate/apply`,
    };

    return {getAttribute: (name) => attrs[name]};
}

// A badge button of a step row: `state` is its data-state (failed, scheduled, waiting); its data-list is the list it loads, as the pane sets it.
function badge(state, eventId = '41') {
    const list = state === 'failed' ? 'failed' : 'waiting';
    const attrs = {'data-state': state, 'data-list': list, 'data-target-url': `/s/campaign-action/${eventId}/${list}/1`};

    return {
        getAttribute: (name) => attrs[name],
        closest: (selector) => {
            assert.strictEqual(selector, '[data-recalc-simulate-url]');

            return eventId === null ? null : row(eventId);
        },
    };
}

function load({withBox = true} = {}) {
    const calls = {box: withBox ? recalcBox() : null};
    let clickHandler;
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.ok(!capture, 'bubble phase, like the pane'); clickHandler = fn; },
        createElement: () => element(),
        getElementById: (id) => (id === 'patches-wait-recalc' ? calls.box : null),
    };
    const window  = {};
    const Mautic  = {patchesActionsLoad: (url) => { calls.reloaded = url; }, processAjaxError: (...a) => { calls.error = a; },
        addFlashMessage: (text) => `flash:${text}`, setFlashes: (flash) => { calls.flash = flash; }};
    const mQuery  = {
        getJSON: (url, cb) => { calls.json = url; calls.jsonCb = cb; return {fail: (fn) => { calls.jsonFail = fn; }}; },
        ajax: (options) => { calls.ajax = options; const chain = {done: (fn) => { calls.ajaxDone = fn; return chain; }, fail: (fn) => { calls.ajaxFail = fn; return chain; }}; return chain; },
    };

    vm.runInNewContext(SCRIPT, {window, document, Mautic, mQuery});

    function click(button) {
        clickHandler({target: {closest: (selector) => {
            assert.strictEqual(selector, '#patches-actions-container button.patches-actions-badge');

            return button;
        }}});
    }

    return {calls, click, window};
}

const messages = (el) => el.children.map((child) => child.textContent || child.children.map((li) => li.textContent).join('|'));
// which of the three buttons can be seen
const visible = (box) => ['start', 'apply', 'cancel'].filter((name) => !box[name].hidden);
const plan    = {summary: {total: 5, changed: 3, leaving_now: 2, unchanged: 0, without_date: 0}, canApply: true};

test('the box starts hidden and shows when the waiting list of a Wait step is loaded, with only Recalculate', () => {
    const t = load();
    t.calls.box.hidden = true;

    t.click(badge('waiting'));

    assert.strictEqual(t.calls.box.hidden, false);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.box.out.hidden, true);
});

test('the clock (scheduled) loads the same waiting list, so it shows the box too', () => {
    const t = load();
    t.calls.box.hidden = true;

    t.click(badge('scheduled'));

    assert.strictEqual(t.calls.box.hidden, false);
});

test('the failed list hides the box: the recalculation only changes who waits', () => {
    const t = load();
    t.click(badge('waiting'));

    t.click(badge('failed'));

    assert.strictEqual(t.calls.box.hidden, true);
});

test('a step without the urls (not a Wait step, or no edit access) hides the box', () => {
    const t = load();
    t.click(badge('waiting'));

    t.click(badge('waiting', null));

    assert.strictEqual(t.calls.box.hidden, true);
});

test('a click that is not on a badge button, or a page without the box, does nothing', () => {
    const t = load();
    t.calls.box.hidden = true;
    t.click(null);
    assert.strictEqual(t.calls.box.hidden, true);

    const noBox = load({withBox: false});
    noBox.click(badge('waiting'));
    assert.strictEqual(noBox.calls.json, undefined);
});

test('the handler is added once, however many pages inject the script', () => {
    const t = load();
    let added = 0;

    assert.strictEqual(t.window.mauticPatchesWaitRecalc, true);
    vm.runInNewContext(SCRIPT, {window: t.window, document: {addEventListener: () => { added += 1; }}, Mautic: {}});

    assert.strictEqual(added, 0);
});

test('Recalculate asks the simulation of the step on screen, shows "Recalculating..." while it runs and then leaves only Apply and Cancel, under the message', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.click(badge('waiting', '77'));

    t.calls.box.start.onclick();
    assert.strictEqual(t.calls.json, '/s/campaign-wait/77/recalculate/simulate', 'the last loaded step');
    assert.strictEqual(t.calls.box.start.disabled, true, 'the button waits for the answer');
    assert.strictEqual(t.calls.box.running.hidden, false, 'the line "<spinner> Recalculating..." is shown');
    assert.strictEqual(t.calls.box.out.hidden, true, 'in place of the message');
    t.calls.jsonCb(plan);

    assert.strictEqual(t.calls.box.running.hidden, true, 'the running line gives way to the result');
    assert.strictEqual(t.calls.box.out.hidden, false);
    assert.deepStrictEqual(messages(t.calls.box.out), ['Com a regra atual:', '3 mudam|2 saem agora']);
    assert.deepStrictEqual(visible(t.calls.box), ['apply', 'cancel'], 'Recalculate gives way to Apply and Cancel');
});

test('loading another list clears the last message and goes back to Recalculate', () => {
    const t = load();
    t.click(badge('waiting'));
    t.calls.box.start.onclick();
    t.calls.jsonCb(plan);

    t.click(badge('waiting', '77'));

    assert.strictEqual(t.calls.box.out.hidden, true);
    assert.deepStrictEqual(t.calls.box.out.children, []);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
});

test('Cancel goes back: the message hidden and Recalculate alone, nothing written', () => {
    const t = load();
    t.click(badge('waiting'));
    t.calls.box.start.onclick();
    t.calls.jsonCb(plan);

    t.calls.box.cancel.onclick();

    assert.strictEqual(t.calls.box.out.hidden, true);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.ajax, undefined);
});

test('nothing to change: a sentence and Recalculate stays, no Apply', () => {
    const t = load();
    t.click(badge('waiting'));

    t.calls.box.start.onclick();
    t.calls.jsonCb({summary: {total: 3, changed: 0, leaving_now: 0, unchanged: 3, without_date: 0}, canApply: false});

    assert.deepStrictEqual(messages(t.calls.box.out), ['Nada a alterar.']);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
});

test('Apply posts to the apply route of the step, says how many were recalculated, reloads the list on screen and goes back to Recalculate', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.calls.box.start.onclick();
    t.calls.jsonCb(plan);

    t.calls.box.apply.onclick();
    assert.strictEqual(t.calls.ajax.type, 'POST');
    assert.strictEqual(t.calls.ajax.url, '/s/campaign-wait/41/recalculate/apply');
    assert.strictEqual(t.calls.box.apply.disabled, true, 'no second post while the first runs');
    t.calls.ajaxDone({applied: 5, summary: {}});

    assert.deepStrictEqual(messages(t.calls.box.out), ['5 recalculadas']);
    assert.strictEqual(t.calls.box.out.children[0].className, 'patches-wait-recalc-applied');
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
    assert.strictEqual(t.calls.reloaded, '/s/campaign-action/41/waiting/1', 'the table shows the new state');
});

test('a failed simulation or application goes through core\'s error handling and the buttons can be used again', () => {
    const t = load();
    t.click(badge('waiting'));

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
    assert.strictEqual(t.calls.reloaded, undefined, 'nothing written, nothing reloaded');
});

test('a simulation that answers after another step was loaded leaves the box alone: no Apply that would post for a step not simulated', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.calls.box.start.onclick();
    const late = t.calls.jsonCb;

    t.click(badge('waiting', '77'));
    late(plan);

    assert.deepStrictEqual(visible(t.calls.box), ['start'], 'still Recalculate alone for the step on screen');
    assert.strictEqual(t.calls.box.out.hidden, true);
});

test('a click on another badge (the failed list) also ends the run: a late answer leaves the hidden box and the table alone', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.calls.box.start.onclick();
    t.calls.jsonCb(plan);
    t.calls.box.apply.onclick();
    const late = t.calls.ajaxDone;

    t.click(badge('failed', '41'));
    late({applied: 5, summary: {}});

    assert.strictEqual(t.calls.box.hidden, true);
    assert.strictEqual(t.calls.reloaded, undefined, 'the failed list on screen is not replaced');
    assert.strictEqual(t.calls.flash, 'flash:5 recalculadas', 'the write is still told, in a notification');
});

test('a late failure of the simulation or of Apply is still shown, but leaves the box of the step on screen alone', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.calls.box.start.onclick();
    const lateSimulation = t.calls.jsonFail;
    t.click(badge('waiting', '77'));
    t.calls.box.start.onclick();
    lateSimulation({status: 500}, 'error', 'Server');

    assert.deepStrictEqual(t.calls.error, [{status: 500}, 'error', 'Server'], 'core shows the error');
    assert.strictEqual(t.calls.box.start.disabled, true, 'the simulation of the step on screen still runs');

    t.calls.jsonCb(plan);
    t.calls.box.apply.onclick();
    const lateApply = t.calls.ajaxFail;
    t.click(badge('waiting', '41'));
    lateApply({status: 403}, 'error', 'Forbidden');

    assert.deepStrictEqual(t.calls.error, [{status: 403}, 'error', 'Forbidden']);
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
});

test('an Apply that answers after another step was loaded tells the write in a notification and does not reload the old list', () => {
    const t = load();
    t.click(badge('waiting', '41'));
    t.calls.box.start.onclick();
    t.calls.jsonCb(plan);
    t.calls.box.apply.onclick();
    const late = t.calls.ajaxDone;

    t.click(badge('waiting', '77'));
    late({applied: 5, summary: {}});

    assert.strictEqual(t.calls.reloaded, undefined, 'the table keeps the list of the step on screen');
    assert.deepStrictEqual(t.calls.box.out.children, []);
    assert.strictEqual(t.calls.flash, 'flash:5 recalculadas');
    assert.deepStrictEqual(visible(t.calls.box), ['start']);
});
