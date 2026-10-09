// Runs the REAL script that ContactListClickSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: the click handler it registers on the document, links inside and outside the contact list table,
// window.open and Mautic.ajaxifyModal as recorders.
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
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/ContactListClickSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\ContactListClickSubscriber::script('/s/contact-details/', 'Detalhes do contato');`,
], {encoding: 'utf8'});

// An anchor: href, whether it sits in the contact table, whether it sits in a row's options menu.
function link(href, {inTable = true, inMenu = false} = {}) {
    return {
        getAttribute: (name) => (name === 'href' ? href : null),
        closest: (selector) => (selector === '.page-list-actions' ? (inMenu ? {} : null) : null),
        inTable,
    };
}

function load() {
    const calls = {open: [], modal: [], created: []};
    let handler;
    const window = {open: (...a) => calls.open.push(a)};
    const document = {
        addEventListener: (type, fn, capture) => { assert.strictEqual(type, 'click'); assert.strictEqual(capture, true); handler = fn; },
        createElement: () => {
            const el = {attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }};
            calls.created.push(el);

            return el;
        },
    };
    const Mautic = {ajaxifyModal: (el) => calls.modal.push(el.attrs)};

    vm.runInNewContext(SCRIPT, {window, document, Mautic});

    // A click on an element: only a link inside the contact table is found by the table selector.
    function click(anchor, extra = {}) {
        const event = {
            button: 0, prevented: false, stopped: false,
            target: {closest: (sel) => { assert.strictEqual(sel, '#leadTable a[href]'); return anchor && anchor.inTable ? anchor : null; }},
            preventDefault() { this.prevented = true; },
            stopImmediatePropagation() { this.stopped = true; },
            ...extra,
        };
        handler(event);

        return event;
    }

    return {calls, click, window};
}

test('a click on the name opens the modal with the contact\'s details and stops core\'s handler', () => {
    const {calls, click} = load();
    const event = click(link('/s/contacts/view/185'));

    assert.deepStrictEqual(calls.modal, [{
        href: '/s/contact-details/185', 'data-toggle': 'ajaxmodal', 'data-target': '#MauticSharedModal', 'data-header': 'Detalhes do contato',
    }]);
    assert.deepStrictEqual(calls.open, []);
    assert.ok(event.prevented && event.stopped);
});

test('the Detalhes option of the row menu opens the contact page in a new tab', () => {
    const {calls, click} = load();
    const event = click(link('/s/contacts/view/185', {inMenu: true}));

    assert.deepStrictEqual(calls.open, [['/s/contacts/view/185', '_blank', 'noopener']]);
    assert.deepStrictEqual(calls.modal, []);
    assert.ok(event.prevented && event.stopped);
});

test('a ctrl, cmd or shift click on the name goes to the contact page in a new tab', () => {
    for (const mod of [{ctrlKey: true}, {metaKey: true}, {shiftKey: true}]) {
        const {calls, click} = load();
        click(link('/s/contacts/view/7'), mod);

        assert.deepStrictEqual(calls.open, [['/s/contacts/view/7', '_blank', 'noopener']]);
        assert.deepStrictEqual(calls.modal, []);
    }
});

test('other links, other pages and other buttons are left alone', () => {
    const {calls, click} = load();

    for (const [anchor, extra] of [
        [link('/s/contacts/edit/185'), {}],
        [link('/s/contacts/view/185/'), {}],
        [link('/s/contacts/view/abc'), {}],
        [link('/s/contacts/view/185', {inTable: false}), {}],
        [null, {}],
        [link('/s/contacts/view/185'), {button: 1}],
    ]) {
        const event = click(anchor, extra);
        assert.ok(!event.prevented && !event.stopped);
    }

    assert.deepStrictEqual(calls.open, []);
    assert.deepStrictEqual(calls.modal, []);
});

test('the script is only installed once, even if the page header loads it again', () => {
    const window = {};
    const document = {addEventListener: () => { document.n = (document.n || 0) + 1; }, createElement: () => ({})};

    vm.runInNewContext(SCRIPT, {window, document, Mautic: {}});
    vm.runInNewContext(SCRIPT, {window, document, Mautic: {}});

    assert.strictEqual(document.n, 1);
});
