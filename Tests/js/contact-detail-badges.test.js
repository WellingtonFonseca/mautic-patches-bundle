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

/** A contact page: a toolbar with the Editar button and a label of core per blocked word. */
function page(labels = [], path = '/s/contacts/view/7') {
    const bar = node('div'), edit = node('a');
    bar.appendChild(edit);
    const doc = {
        readyState: 'complete',
        location: {pathname: path},
        querySelector: (s) => (s === '#toolbar .std-toolbar' ? bar : null),
        querySelectorAll: (s) => (s === '[id^="bounceLabel"] .label' ? labels.map((t) => ({textContent: t})) : []),
        createElement: (tag) => node(tag)
    };

    return {doc, bar, edit};
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

test('blockedChannels: the channel is the last word of each label, in lower case, in any language', () => {
    const {doc} = page(['\n  Não Contatar: EMAIL\n  ', 'Bounced: SMS']);

    assert.deepStrictEqual(api.blockedChannels(doc), {email: true, sms: true});
});

test('blockedChannels: no label, nothing blocked', () => {
    assert.deepStrictEqual(api.blockedChannels(page([]).doc), {});
});

test('blockedChannels: an empty label adds no channel', () => {
    assert.deepStrictEqual(api.blockedChannels(page(['  ']).doc), {});
});

test('addContactDetailBadges: three badges go before the Editar button: Email, SMS, HSM, all green when nothing is blocked', () => {
    const {doc, bar, edit} = page([]);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);

    assert.strictEqual(bar.children.length, 2);
    assert.strictEqual(bar.children[1], edit, 'the box is before the button');
    const box = bar.children[0];
    assert.strictEqual(box.className, 'patches-contact-badges');
    assert.deepStrictEqual(box.children.map((b) => b.attrs.title), ['Email', 'SMS', 'HSM']);
    assert.ok(box.children.every((b) => b.className.includes('patches-badge--production')));
});

test('addContactDetailBadges: a blocked email makes only the email badge red; a blocked sms makes SMS and HSM red', () => {
    const colors = (box) => box.children.map((b) => (b.className.includes('patches-badge--failed') ? 'red' : 'green'));

    const mail = page(['Do Not Contact: EMAIL']);
    api.addContactDetailBadges(mail.doc, cfg);
    assert.deepStrictEqual(colors(mail.bar.children[0]), ['red', 'green', 'green']);

    const sms = page(['Do Not Contact: SMS']);
    api.addContactDetailBadges(sms.doc, cfg);
    assert.deepStrictEqual(colors(sms.bar.children[0]), ['green', 'red', 'red']);
});

test('addContactDetailBadges: a box that is already right is not written again (a write wakes the observer and would loop)', () => {
    const {doc, bar} = page(['Do Not Contact: EMAIL']);

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);
    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.strictEqual(bar.children.length, 2, 'one box only');
});

test('addContactDetailBadges: when the label is removed the box is replaced by a green one, not added twice', () => {
    const labels = ['Do Not Contact: EMAIL'];
    const {doc, bar} = page(labels);
    api.addContactDetailBadges(doc, cfg);

    labels.length = 0;
    assert.strictEqual(api.addContactDetailBadges(doc, cfg), true);

    assert.strictEqual(bar.children.length, 2);
    assert.ok(bar.children[0].children.every((b) => b.className.includes('patches-badge--production')));
});

test('addContactDetailBadges: nothing outside the page of a contact', () => {
    const {doc, bar} = page([], '/s/contacts');

    assert.strictEqual(api.addContactDetailBadges(doc, cfg), false);
    assert.strictEqual(bar.children.length, 1);
});

test('addContactDetailBadges: nothing while the page is still being parsed, and nothing without the toolbar', () => {
    const loading = page([]);
    loading.doc.readyState = 'loading';
    loading.doc.querySelector = () => { throw new Error('the page was read while it loads'); };
    assert.strictEqual(api.addContactDetailBadges(loading.doc, cfg), false);

    const none = page([]);
    none.doc.querySelector = () => null;
    assert.strictEqual(api.addContactDetailBadges(none.doc, cfg), false);
});
