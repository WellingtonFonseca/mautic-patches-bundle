// Test-tags: areas=campaign risks=FLUXO
// Runs the REAL script of the "Etapas" tab of the campaign page (taken from CampaignStepsTabScript::SCRIPT, not copied) against a small fake
// browser: the click handler on the document, the badge buttons of a step row, the title above the table and the table itself.
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
const CLASS_FILE = path.resolve(__dirname, '../../Service/CampaignStepsTabScript.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\Service\\CampaignStepsTabScript::SCRIPT;`,
], {encoding: 'utf8'});

// A DOM element as much as the script uses it. innerHTML throws: the step name is typed by users and must only go in as text.
function element(tag = 'div') {
    const classes = new Set();
    const el = {tag, attrs: {}, children: [], hidden: false, _text: '',
        classList: {add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c),
            forEach: (fn) => [...classes].forEach(fn), get length() { return classes.size; }, [Symbol.iterator]: () => classes[Symbol.iterator]()},
        getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
        setAttribute(k, v) { this.attrs[k] = v; },
        hasAttribute(k) { return k in this.attrs; },
        appendChild(child) { this.children.push(child); return child; }};
    Object.defineProperty(el, 'className', {get: () => [...classes].join(' '), set: (v) => { classes.clear(); String(v).split(' ').filter(Boolean).forEach((c) => classes.add(c)); }});
    Object.defineProperty(el, 'textContent', {get: () => el._text, set: (v) => { el._text = String(v); el.children = []; }});
    Object.defineProperty(el, 'innerHTML', {get: () => '', set: () => { throw new Error('innerHTML must not be used'); }});

    return el;
}

// A badge button of a step row: the modifier class and the icon the pane draws, the word of data-heading, and the row with the step name.
function button({state = 'failed', modifier = 'failed', icon = 'ri-error-warning-line', heading = 'Falhou', name = 'Enviar HSM', eventId = '41'} = {}) {
    const b = element('button');
    b.className = `patches-badge patches-badge--${modifier} patches-steps-badge`;
    b.attrs = {'data-state': state, 'data-heading': heading, 'data-target-url': `/s/campaign-step/${eventId}/${state}/1`};
    const i = element('i');
    i.className = icon;
    const row = element();
    row.attrs = {'data-event-id': eventId, 'data-step-name': name};
    b.querySelector = (selector) => { assert.strictEqual(selector, 'i'); return i; };
    b.closest = (selector) => { assert.strictEqual(selector, '[data-event-id]'); return row; };

    return b;
}

function load() {
    const calls = {gets: []};
    const heading = element(); heading.hidden = true;
    const table   = element(); table.attrs = {'data-target-url': '/s/campaign-step/41/none/1'};
    const buttons = [];
    let clickHandler; let shownHandler;
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.ok(!capture, 'bubble phase, like the Recalculate script'); clickHandler = fn; },
        createElement: (tag) => element(tag),
        getElementById: (id) => ({'patches-steps-heading': heading, 'patches-steps-table': table}[id] || null),
        querySelectorAll: () => buttons.filter((b) => b.classList.contains('active')),
    };
    const window = {};
    const Mautic = {
        processPageContent: (response) => { calls.filled = response; }, processAjaxError: (...a) => { calls.error = a; },
    };
    const mQuery = Object.assign((target) => { assert.strictEqual(target, document); return {on: (type, selector, fn) => { assert.strictEqual(type, 'shown.bs.tab'); shownHandler = fn; }}; }, {
        ajax: (options) => {
            const request = {url: options.url, options, cb: null, always: null, failed: null};
            const chain = {done: (fn) => { request.cb = fn; return chain; }, fail: (fn) => { request.failed = fn; return chain; }, always: (fn) => { request.always = fn; return chain; }};
            calls.gets.push(request);

            return chain;
        },
    });

    vm.runInNewContext(SCRIPT, {window, document, Mautic, mQuery});

    // the answer of a request: the callback, then always (as jQuery does)
    const answer = (request, response = {newContent: 'x'}) => { request.cb(response); request.always(); };
    const click  = (b) => { if (b && !buttons.includes(b)) { buttons.push(b); } clickHandler({target: {closest: () => b}}); };

    return {calls, heading, table, window, Mautic, click, answer, shown: () => shownHandler()};
}

test('a click on the failed badge fills the title with the red badge, the word and the step name', () => {
    const t = load();

    t.click(button());

    assert.strictEqual(t.heading.hidden, false);
    const [badge, title] = t.heading.children;
    assert.deepStrictEqual(badge.className.split(' ').sort(), ['patches-badge', 'patches-badge--failed', 'patches-steps-heading-badge']);
    assert.strictEqual(badge.children[0].className, 'ri-error-warning-line');
    assert.strictEqual(badge.children[1].textContent, 'Falhou');
    assert.strictEqual(title.textContent, 'Enviar HSM');
});

test('a click on the waiting badge gives the blue hourglass badge with its own word', () => {
    const t = load();

    t.click(button({state: 'waiting', modifier: 'info', icon: 'ri-hourglass-line', heading: 'Em espera', name: 'Esperar'}));

    const [badge, title] = t.heading.children;
    assert.ok(badge.classList.contains('patches-badge--info'));
    assert.strictEqual(badge.children[0].className, 'ri-hourglass-line');
    assert.strictEqual(badge.children[1].textContent, 'Em espera');
    assert.strictEqual(title.textContent, 'Esperar');
});

test('the step name goes in as text, never as HTML', () => {
    const t = load();

    t.click(button({name: '<b>x</b>'}));

    assert.strictEqual(t.heading.children[1].textContent, '<b>x</b>');
});

test('a second click replaces the title, it does not add to it', () => {
    const t = load();

    t.click(button({name: 'A'}));
    t.click(button({state: 'waiting', modifier: 'info', icon: 'ri-hourglass-line', heading: 'Em espera', name: 'B', eventId: '42'}));

    assert.strictEqual(t.heading.children.length, 2);
    assert.strictEqual(t.heading.children[1].textContent, 'B');
});

test('a click loads the list with core\'s bar (showLoadingBar) and the dimmed table, and the answer undoes the dimming', () => {
    const t = load();

    t.click(button());

    assert.strictEqual(t.calls.gets[0].options.showLoadingBar, true);
    assert.strictEqual(t.calls.gets[0].options.type, 'GET');
    assert.ok(t.table.classList.contains('patches-steps-table--loading'));
    assert.strictEqual(t.calls.gets[0].url, '/s/campaign-step/41/failed/1');

    t.answer(t.calls.gets[0]);

    assert.ok(!t.table.classList.contains('patches-steps-table--loading'));
    assert.strictEqual(t.calls.filled.target, '#patches-steps-table');
});

test('the table is never emptied while it loads, and no spinner icon is drawn', () => {
    const t = load();
    t.table._text = 'old list';

    t.click(button());

    assert.strictEqual(t.table._text, 'old list');
});

test('two quick clicks: the late first answer fills nothing and does not undo the dimming of the second', () => {
    const t = load();
    t.click(button({eventId: '41'}));
    t.click(button({state: 'waiting', modifier: 'info', eventId: '42'}));

    t.answer(t.calls.gets[0], {newContent: 'first'});

    assert.strictEqual(t.calls.filled, undefined);
    assert.ok(t.table.classList.contains('patches-steps-table--loading'));

    t.answer(t.calls.gets[1], {newContent: 'second'});

    assert.strictEqual(t.calls.filled.newContent, 'second');
    assert.ok(!t.table.classList.contains('patches-steps-table--loading'));
});

test('a failed list hides the title: it would name a step whose list is not on screen', () => {
    const t = load();
    t.click(button());
    assert.strictEqual(t.heading.hidden, false);

    t.calls.gets[0].failed({status: 500}, 'error', 'Server Error');
    t.calls.gets[0].always();

    assert.strictEqual(t.heading.hidden, true);
});

test('a failed first click does not hide the title of a newer click', () => {
    const t = load();
    t.click(button({name: 'A'}));
    t.click(button({name: 'B', eventId: '42'}));

    t.calls.gets[0].failed({status: 500}, 'error', 'Server Error');
    t.calls.gets[0].always();

    assert.strictEqual(t.heading.hidden, false);
    assert.strictEqual(t.heading.children[1].textContent, 'B');
});

test('a reload after the table is gone (the user left the page) does nothing and does not throw', () => {
    const Mautic = {};
    vm.runInNewContext(SCRIPT, {window: {}, document: {addEventListener: () => {}, getElementById: () => null},
        Mautic, mQuery: Object.assign(() => ({on: () => {}}), {ajax: () => assert.fail('no request without a table')})});

    assert.doesNotThrow(() => Mautic.patchesStepsLoad('/s/campaign-step/41/waiting/1'));
});

test('a click and then a show of the tab: the empty list does not load over the clicked one', () => {
    const t = load();
    t.click(button());

    t.shown();

    assert.deepStrictEqual(t.calls.gets.map((g) => g.url), ['/s/campaign-step/41/failed/1']);
});

test('a reload of the Recalculate box marks the table as loaded too', () => {
    const t = load();

    t.Mautic.patchesStepsLoad('/s/campaign-step/41/waiting/1');
    t.shown();

    assert.deepStrictEqual(t.calls.gets.map((g) => g.url), ['/s/campaign-step/41/waiting/1']);
});

test('a failed request goes to core\'s error handling and undoes the dimming', () => {
    const t = load();
    t.click(button());

    t.calls.gets[0].failed({status: 403}, 'error', 'Forbidden');
    t.calls.gets[0].always();

    assert.deepStrictEqual(t.calls.error, [{status: 403}, 'error', 'Forbidden']);
    assert.ok(!t.table.classList.contains('patches-steps-table--loading'));
});

test('the Recalculate loader reloads the list and keeps the title', () => {
    const t = load();
    t.click(button());

    t.Mautic.patchesStepsLoad('/s/campaign-step/41/waiting/1');

    assert.strictEqual(t.calls.gets[1].url, '/s/campaign-step/41/waiting/1');
    assert.strictEqual(t.heading.children[1].textContent, 'Enviar HSM');
    assert.strictEqual(t.heading.hidden, false);
});

test('showing the tab loads the empty table once, without a title', () => {
    const t = load();

    t.shown();
    t.shown();

    assert.deepStrictEqual(t.calls.gets.map((g) => g.url), ['/s/campaign-step/41/none/1']);
    assert.strictEqual(t.heading.hidden, true);
});

test('a click that is not on a badge button does nothing', () => {
    const t = load();

    t.click(null);

    assert.deepStrictEqual(t.calls.gets, []);
    assert.strictEqual(t.heading.hidden, true);
});

test('the script runs once per page', () => {
    const t = load();
    const gets = t.calls.gets.length;

    vm.runInNewContext(SCRIPT, {window: t.window, document: {addEventListener: () => assert.fail('a second handler')}, Mautic: t.Mautic, mQuery: () => assert.fail('a second handler')});

    assert.strictEqual(t.calls.gets.length, gets);
});
