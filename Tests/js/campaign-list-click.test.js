// Test-tags: areas=campaign risks=FLUXO
// Runs the REAL script that CampaignListClickSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the click handler on the document, links inside and outside the campaign list, Mautic.ajaxifyModal as a recorder.
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
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/CampaignListClickSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignListClickSubscriber::script('/s/campaign-details/', 'Detalhes da campanha');`,
], {encoding: 'utf8'});


function link(href, {inList = true, inMenu = false} = {}) {
    return {
        getAttribute: (name) => (name === 'href' ? href : null),
        closest: (selector) => (selector === '.page-list-actions' ? (inMenu ? {} : null) : null),
        inList,
    };
}

function load(pathname = '/s/campaigns') {
    const modal = [];
    let clickHandler;
    const window = {location: {pathname}};
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.strictEqual(capture, true); clickHandler = fn; },
        createElement: () => ({attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }}),
    };
    const Mautic = {ajaxifyModal: (el) => modal.push(el.attrs)};
    vm.runInNewContext(SCRIPT, {window, document, Mautic});

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

    return {modal, click, window, document};
}

test('a click on a campaign name opens the details modal with the route, the target and the title', () => {
    const t = load();

    const event = t.click(link('/s/campaigns/view/92'));

    assert.strictEqual(event.prevented, true);
    assert.strictEqual(event.stopped, true);
    assert.deepStrictEqual(t.modal, [{
        href: '/s/campaign-details/92', 'data-toggle': 'ajaxmodal', 'data-target': '#MauticSharedModal', 'data-header': 'Detalhes da campanha',
    }]);
});

test('it also works on a later page of the list and with a query on the link', () => {
    const t = load('/s/campaigns/2');

    assert.strictEqual(t.click(link('/s/campaigns/view/5?x=1')).prevented, true);
    assert.strictEqual(t.modal[0].href, '/s/campaign-details/5');
});

test('the Details option of the row menu goes to the page: not touched', () => {
    const t = load();

    const event = t.click(link('/s/campaigns/view/92', {inMenu: true}));

    assert.strictEqual(event.prevented, false);
    assert.deepStrictEqual(t.modal, []);
});

test('ctrl, cmd, shift and the middle button are left to the browser', () => {
    const t = load();

    for (const extra of [{ctrlKey: true}, {metaKey: true}, {shiftKey: true}, {button: 1}]) {
        assert.strictEqual(t.click(link('/s/campaigns/view/92'), extra).prevented, false);
    }
    assert.deepStrictEqual(t.modal, []);
});

test('other links and other places are not touched', () => {
    const t = load();

    assert.strictEqual(t.click(link('/s/campaigns/edit/92')).prevented, false);
    assert.strictEqual(t.click(link('/s/campaigns/view/92', {inList: false})).prevented, false);
    assert.strictEqual(load('/s/segments').click(link('/s/campaigns/view/92')).prevented, false, 'a campaign link on another page');
    assert.deepStrictEqual(t.modal, []);
});

test('the script installs its handler only once per page', () => {
    let added = 0;
    const window = {location: {pathname: '/s/campaigns'}};
    const document = {addEventListener: () => { added++; }, createElement: () => ({})};
    vm.runInNewContext(SCRIPT, {window, document, Mautic: {}});
    vm.runInNewContext(SCRIPT, {window, document, Mautic: {}});

    assert.strictEqual(added, 1);
});
