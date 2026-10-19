// Runs the REAL script that SegmentListClickSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the click handler on the document, links inside and outside the segment list, Mautic.ajaxifyModal as a
// recorder, and the "shown.bs.tab" handler that loads the Contacts tab and the campaign shares.
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
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/SegmentListClickSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\SegmentListClickSubscriber::script('/s/segment-details/', 'Detalhes do segmento');`,
], {encoding: 'utf8'});

// An anchor: href, whether it sits in the list wrapper, whether it sits in a row's options menu.
function link(href, {inList = true, inMenu = false} = {}) {
    return {
        getAttribute: (name) => (name === 'href' ? href : null),
        closest: (selector) => (selector === '.page-list-actions' ? (inMenu ? {} : null) : null),
        inList,
    };
}

function load(pathname = '/s/segments') {
    const calls = {modal: [], get: [], action: []};
    let clickHandler;
    let tabHandler;
    const window = {location: {pathname}};
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.strictEqual(capture, true); clickHandler = fn; },
        createElement: () => ({attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }}),
    };
    const Mautic = {
        ajaxifyModal: (el) => calls.modal.push(el.attrs),
        processPageContent: (r) => { calls.processed = r; },
        ajaxActionRequest: (action, data, cb, a, b, method) => calls.action.push({action, data, cb, method}),
    };
    const shares = [{attr: {'data-value': '3'}, text: ''}, {attr: {'data-value': '4'}, text: '12'}];
    const html = {};
    const mQuery = (arg) => {
        if (arg && arg.fake) { return arg; }
        if (arg === '.patches-campaign-share-stat') {
            return {each(fn) { shares.forEach((s) => fn.call({s})); }};
        }
        if (arg === '#contacts-container') {
            return {data: () => '/s/segments/contacts/9/1'};
        }
        if (typeof arg === 'string' && arg.startsWith('#patches-campaign-share-stat-')) {
            return {html: (v) => { html[arg] = v; }};
        }
        if (arg && arg.s) {
            return {text: () => arg.s.text, attr: (k) => arg.s.attr[k]};
        }

        return {};
    };
    mQuery.get = (url, cb) => { calls.get.push(url); calls.getCb = cb; };
    mQuery.fn = {};
    const tabLink = (kind) => {
        const store = {};
        const a = {fake: true, data(k, v) { if (v === undefined) { return k === 'patches-load' ? kind : store[k]; } store[k] = v; return a; },
            closest: () => ({data: () => 9})};

        return a;
    };
    mQuery.documentOn = null;
    const documentWrapper = {fake: true, on: (ev, sel, fn) => { assert.strictEqual(ev, 'shown.bs.tab'); tabHandler = fn; }};
    const sandboxMQuery = (arg) => (arg === document ? documentWrapper : mQuery(arg));
    sandboxMQuery.get = mQuery.get;

    vm.runInNewContext(SCRIPT, {window, document, Mautic, mQuery: sandboxMQuery});

    function click(anchor, extra = {}) {
        const event = {
            button: 0, ctrlKey: false, metaKey: false, shiftKey: false, ...extra, prevented: false, stopped: false,
            preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; },
            target: {closest: (selector) => {
                assert.strictEqual(selector, '#page-list-wrapper a[href]');

                return anchor.inList ? anchor : null;
            }},
        };
        clickHandler(event);

        return event;
    }

    return {calls, click, html, tabLink, showTab: (a) => tabHandler.call(a), shares, window};
}

test('a click on a segment name opens the details modal with the route, the target and the title', () => {
    const t = load();

    const event = t.click(link('/s/segments/view/92'));

    assert.strictEqual(event.prevented, true);
    assert.strictEqual(event.stopped, true);
    assert.deepStrictEqual(t.calls.modal, [{
        href: '/s/segment-details/92', 'data-toggle': 'ajaxmodal', 'data-target': '#MauticSharedModal', 'data-header': 'Detalhes do segmento',
    }]);
});

test('it also works on a later page of the list', () => {
    const t = load('/s/segments/2');

    assert.strictEqual(t.click(link('/s/segments/view/5')).prevented, true);
});

test('the Details option of the row menu goes to the page: not touched', () => {
    const t = load();

    const event = t.click(link('/s/segments/view/92', {inMenu: true}));

    assert.strictEqual(event.prevented, false);
    assert.deepStrictEqual(t.calls.modal, []);
});

test('ctrl, cmd, shift and the middle button are left to the browser', () => {
    const t = load();

    for (const extra of [{ctrlKey: true}, {metaKey: true}, {shiftKey: true}, {button: 1}]) {
        assert.strictEqual(t.click(link('/s/segments/view/92'), extra).prevented, false);
    }
    assert.deepStrictEqual(t.calls.modal, []);
});

test('other links and other places are not touched', () => {
    const t = load();

    assert.strictEqual(t.click(link('/s/segments/edit/92')).prevented, false);
    assert.strictEqual(t.click(link('/s/segments/view/92', {inList: false})).prevented, false);
    assert.strictEqual(load('/s/campaigns').click(link('/s/segments/view/92')).prevented, false, 'a segment link on another page');
    assert.deepStrictEqual(t.calls.modal, []);
});

test('the Contacts tab loads once through core\'s own loader of #contacts-container', () => {
    const t = load();
    const tab = t.tabLink('contacts');

    t.showTab(tab);
    t.showTab(tab);

    assert.strictEqual(JSON.stringify(t.calls.get), JSON.stringify(['/s/segments/contacts/9/1']));
    t.calls.getCb({html: 'x'});
    assert.strictEqual(t.calls.processed.target, '#contacts-container');
});

test('the campaign share tab asks only for the shares that are empty, once, and fills them', () => {
    const t = load();
    const tab = t.tabLink('shares');

    t.showTab(tab);
    t.showTab(tab);

    assert.strictEqual(t.calls.action.length, 1);
    assert.strictEqual(t.calls.action[0].action, 'lead:getCampaignShareStats');
    assert.strictEqual(JSON.stringify(t.calls.action[0].data), JSON.stringify({ids: ['3'], entityId: 9}));
    assert.strictEqual(t.calls.action[0].method, 'GET');
    t.calls.action[0].cb({success: true, stats: [{id: 3, data: 41}]});
    assert.strictEqual(t.html['#patches-campaign-share-stat-3'], 41);
});
