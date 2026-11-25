// Test-tags: areas=contacts risks=UI
// The badges of the contact page (Resources/js/contact-detail-badges.js): which channels are blocked, and what is written in the toolbar.
// The look is checked in the browser (scripts/5/e2e/tests/contact-detail-status.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/contact-detail-badges.js');

const cfg = {contactEmail: 'Email', contactSms: 'SMS', contactHsm: 'HSM'};

/** A fake DOM node with just what the script uses. */
function node(name) {
    return {
        name, attrs: {}, children: [], className: '', _text: '',
        get textContent() { return this._text; },
        set textContent(v) { this._text = v; },
        setAttribute(k, v) { this.attrs[k] = v; },
        getAttribute(k) { return this.attrs[k]; },
        appendChild(child) { child.parentNode = this; this.children.push(child); },
        removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; },
        insertBefore(child, ref) { child.parentNode = this; this.children.splice(ref ? this.children.indexOf(ref) : this.children.length, 0, child); },
        get firstChild() { return this.children[0] || null; },
        querySelector(selector) { return selector === '.patches-contact-badges' ? this.children.find((c) => c.className === 'patches-contact-badges') || null : null; }
    };
}

/** The header component the server renders: a host with the title and the hidden marker (the badges box comes later). */
function header(title, inModal = false) {
    const host = node('div'), name = node('h1'), marker = node('span');
    name.className = 'patches-modal-name';
    name._text = title;
    host.inModal = inModal;
    host.className = 'patches-contact-header';
    host.appendChild(name);
    host.appendChild(marker);
    Object.defineProperty(name, 'nextSibling', {get: () => host.children[host.children.indexOf(name) + 1] || null});
    host.contains = (n) => n === host || host.children.includes(n);
    host.querySelector = (s) => {
        if (s === '.patches-modal-name') { return name; }
        if (s === '.patches-details-badges') { return host.children.find((c) => c.className === 'patches-details-badges') || null; }

        return null;
    };

    return host;
}

/**
 * A contact page: core's heading (h1.page-header-title) in a toolbar, a header component (placed by the script in place of the heading) and a label of core per blocked word.
 * `modal` adds a second header component inside #MauticSharedModal, which the page script must leave alone.
 */
function page(labels = [], path = '/s/contacts/view/7', {title = 'A-100 - Ana Souza', modal = false, coreText = 'Ana Souza'} = {}) {
    const bar = node('div'), core = node('h1'), host = header(title), modalHost = modal ? header('Modal title', true) : null;
    core.className = 'page-header-title';
    core._text = '\n  ' + coreText + '\n';
    bar.replaceChild = (n, old) => { bar.children[bar.children.indexOf(old)] = n; n.parentNode = bar; old.parentNode = null; };
    bar.appendChild(core);
    const hosts = [host].concat(modalHost ? [modalHost] : []);
    const doc = {
        readyState: 'complete',
        title: 'Contact · ' + coreText + ' | Mautic',
        location: {pathname: path},
        querySelector: (s) => {
            if (s === '.patches-contact-header:not([data-placed]):not(#MauticSharedModal *)') { return hosts.find((h) => !h.inModal && !h.attrs['data-placed']) || null; }
            if (s === '.patches-contact-header[data-placed]') { return hosts.find((h) => h.attrs['data-placed']) || null; }

            return null;
        },
        querySelectorAll: (s) => {
            if (s === 'h1.page-header-title') { return bar.children.includes(core) ? [core] : []; }
            if (s === '[id^="bounceLabel"] .label') { return labels.map((t) => ({textContent: t})); }

            return [];
        },
        createElement: (tag) => node(tag)
    };

    return {doc, bar, core, host, modalHost};
}

test('isContactViewPath: the page of one contact, also under a subdirectory or index.php; not the list or another page', () => {
    assert.strictEqual(api.isContactViewPath('/s/contacts/view/5'), true);
    assert.strictEqual(api.isContactViewPath('/mautic/s/contacts/view/5'), true);
    assert.strictEqual(api.isContactViewPath('/index.php/s/contacts/view/5'), true);
    assert.strictEqual(api.isContactViewPath('/s/contacts'), false);
    assert.strictEqual(api.isContactViewPath('/s/contacts/view/'), false);
    assert.strictEqual(api.isContactViewPath('/s/contacts/edit/5'), false);
    assert.strictEqual(api.isContactViewPath('/s/segments/view/5'), false);
});

test('pageBlockedChannels: the channel is the last word of each label, in lower case, in any language', () => {
    const {doc} = page(['\n  Não Contatar: EMAIL\n  ', 'Bounced: SMS']);

    assert.deepStrictEqual(api.pageBlockedChannels(doc), {email: true, sms: true});
});

test('pageBlockedChannels: no label, nothing blocked', () => {
    assert.deepStrictEqual(api.pageBlockedChannels(page([]).doc), {});
});

test('pageBlockedChannels: an empty label adds no channel', () => {
    assert.deepStrictEqual(api.pageBlockedChannels(page(['  ']).doc), {});
});

test('addContactDetailBadges: the header takes the place of core\'s heading, the title of the page follows it, and the badges come after the title: Email, SMS, HSM, all green when nothing is blocked', () => {
    const {doc, bar, core, host} = page([]);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);

    assert.deepStrictEqual(bar.children, [host], 'core\'s heading is gone, the header is in its place');
    assert.notStrictEqual(host.children[0], core);
    assert.strictEqual(host.attrs['data-placed'], '1');
    assert.strictEqual(doc.title, 'A-100 - Ana Souza | Mautic');
    assert.strictEqual(host.children[0].className, 'patches-modal-name');
    const box = host.children[1];
    assert.strictEqual(box.className, 'patches-details-badges', 'right after the title');
    assert.deepStrictEqual(box.children.map((b) => b.attrs.title), ['Email', 'SMS', 'HSM']);
    assert.ok(box.children.every((b) => b.className.includes('patches-badge--production')));
});

test('addContactDetailBadges: a blocked email makes only the email badge red; a blocked sms makes SMS and HSM red', () => {
    const colors = (box) => box.children.map((b) => (b.className.includes('patches-badge--failed') ? 'red' : 'green'));

    const mail = page(['Do Not Contact: EMAIL']);
    api.addContactDetailBadges(mail.doc, cfg);
    assert.deepStrictEqual(colors(mail.host.children[1]), ['red', 'green', 'green']);

    const sms = page(['Do Not Contact: SMS']);
    api.addContactDetailBadges(sms.doc, cfg);
    assert.deepStrictEqual(colors(sms.host.children[1]), ['green', 'red', 'red']);
});

test('addContactDetailBadges: the document title is the component\'s name; one that already holds it is not rewritten, and core\'s empty heading does not matter', () => {
    const same = page([]);
    same.doc.title = 'A-100 - Ana Souza | Mautic';
    api.addContactDetailBadges(same.doc, cfg);
    assert.strictEqual(same.doc.title, 'A-100 - Ana Souza | Mautic');

    const blank = page([], '/s/contacts/view/7', {coreText: ''});
    blank.doc.title = 'Mautic';
    api.addContactDetailBadges(blank.doc, cfg);
    assert.strictEqual(blank.doc.title, 'A-100 - Ana Souza | Mautic');
});

test('addContactDetailBadges: a box that is already right is not written again (a write wakes the observer and would loop); the header is placed once', () => {
    const {doc, bar, host} = page(['Do Not Contact: EMAIL']);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);
    const box = host.children[1];
    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.strictEqual(host.children[1], box, 'the same box stays');
    assert.strictEqual(host.children.filter((c) => c.className === 'patches-details-badges').length, 1, 'one box only');
    assert.deepStrictEqual(bar.children, [host]);
});

test('addContactDetailBadges: when the label is removed the box is replaced by a green one, not added twice', () => {
    const labels = ['Do Not Contact: EMAIL'];
    const {doc, host} = page(labels);
    api.addContactDetailBadges(doc, cfg);

    labels.length = 0;
    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);

    assert.strictEqual(host.children.filter((c) => c.className === 'patches-details-badges').length, 1);
    assert.ok(host.children[1].children.every((b) => b.className.includes('patches-badge--production')));
});

test('addContactDetailBadges: the header of the "Ver" modal is excluded: the page header is placed, the modal one is left as it is', () => {
    const {doc, bar, host, modalHost} = page([], '/s/contacts/view/7', {modal: true});

    api.addContactDetailBadges(doc, cfg);

    assert.deepStrictEqual(bar.children, [host]);
    assert.strictEqual(modalHost.attrs['data-placed'], undefined);
    assert.strictEqual(modalHost.parentNode, undefined, 'the modal header was not moved');
    assert.strictEqual(doc.title, 'A-100 - Ana Souza | Mautic', 'the title is the page header\'s, not the modal\'s');
});

test('addContactDetailBadges: only a header in a modal: nothing is placed and the heading of core stays', () => {
    const {doc, bar, core, host, modalHost} = page([]);
    host.inModal = true;
    host.className = 'patches-contact-header';
    assert.ok(modalHost === null);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.deepStrictEqual(bar.children, [core]);
    assert.strictEqual(doc.title, 'Contact · Ana Souza | Mautic');
});

test('addContactDetailBadges: nothing outside the page of a contact', () => {
    const {doc, bar, core} = page([], '/s/contacts');

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.deepStrictEqual(bar.children, [core]);
});

test('addContactDetailBadges: without core\'s heading nothing is placed (the header stays unplaced, to try again)', () => {
    const {doc, bar, core, host} = page([]);
    bar.removeChild(core);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.strictEqual(host.attrs['data-placed'], undefined);
});

test('addContactDetailBadges: nothing while the page is still being parsed', () => {
    const loading = page([]);
    loading.doc.readyState = 'loading';
    loading.doc.querySelector = () => { throw new Error('the page was read while it loads'); };
    assert.strictEqual(api.addContactDetailBadges(loading.doc, cfg), false);
});

/** The header component of the "Ver" modal: a host with the title, the hidden marker and, later, the box of badges. */
function modalHead(blocked, {title = true} = {}) {
    const host = node('div'), name = node('h1'), marker = node('span');
    name.className = 'patches-modal-name';
    Object.defineProperty(name, 'nextSibling', {get: () => host.children[host.children.indexOf(name) + 1] || null});
    marker.attrs['data-blocked'] = blocked;
    if (title) { host.appendChild(name); }
    host.appendChild(marker);
    host.querySelector = (s) => {
        if (s === '.patches-detail-status') { return marker; }
        if (s === '.patches-modal-name') { return title ? name : null; }
        if (s === '.patches-details-badges') { return host.children.find((c) => c.className === 'patches-details-badges') || null; }

        return null;
    };
    const doc = {querySelector: (s) => (s === '.patches-contact-header' ? host : null), createElement: (tag) => node(tag)};

    return {doc, host, name, marker};
}

const colors = (box) => box.children.map((b) => (b.className.includes('patches-badge--failed') ? 'red' : 'green'));

test('renderContactModalHead: the badges go right after the title, before the hidden marker, all green when nothing is blocked', () => {
    const {doc, host, name, marker} = modalHead('');

    assert.strictEqual(api.renderContactModalHead(doc, cfg), true);

    assert.strictEqual(host.children[0], name);
    assert.strictEqual(host.children[1].className, 'patches-details-badges');
    assert.strictEqual(host.children[2], marker);
    assert.deepStrictEqual(host.children[1].children.map((b) => b.attrs.title), ['Email', 'SMS', 'HSM']);
    assert.deepStrictEqual(colors(host.children[1]), ['green', 'green', 'green']);
});

test('renderContactModalHead: a blocked email turns only Email red; a blocked sms turns SMS and HSM red', () => {
    const mail = modalHead('email');
    api.renderContactModalHead(mail.doc, cfg);
    assert.deepStrictEqual(colors(mail.host.children[1]), ['red', 'green', 'green']);

    const sms = modalHead('sms');
    api.renderContactModalHead(sms.doc, cfg);
    assert.deepStrictEqual(colors(sms.host.children[1]), ['green', 'red', 'red']);

    const both = modalHead('email,sms');
    api.renderContactModalHead(both.doc, cfg);
    assert.deepStrictEqual(colors(both.host.children[1]), ['red', 'red', 'red']);
});

test('renderContactModalHead: idempotent: a box already right is not rewritten (data-state), a changed state replaces it, never two boxes', () => {
    const {doc, host, marker} = modalHead('email');

    assert.strictEqual(api.renderContactModalHead(doc, cfg), true);
    const first = host.children[1];
    assert.strictEqual(api.renderContactModalHead(doc, cfg), false);
    assert.strictEqual(host.children[1], first, 'the same box stays');

    marker.attrs['data-blocked'] = 'sms';
    assert.strictEqual(api.renderContactModalHead(doc, cfg), true);
    assert.strictEqual(host.children.filter((c) => c.className === 'patches-details-badges').length, 1);
    assert.deepStrictEqual(colors(host.children[1]), ['green', 'red', 'red']);
});

test('renderContactModalHead: without a title the box goes first in the host', () => {
    const {doc, host} = modalHead('', {title: false});

    assert.strictEqual(api.renderContactModalHead(doc, cfg), true);
    assert.strictEqual(host.children[0].className, 'patches-details-badges');
});

test('renderContactModalHead: no-op without the contact header, or without its marker', () => {
    const none = {querySelector: () => null, createElement: () => { throw new Error('nothing may be created'); }};
    assert.strictEqual(api.renderContactModalHead(none, cfg), false);

    const {doc, host} = modalHead('email');
    host.querySelector = () => null;
    assert.strictEqual(api.renderContactModalHead(doc, cfg), false);
    assert.strictEqual(host.children.length, 2, 'nothing added');
});
