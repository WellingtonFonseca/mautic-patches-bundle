// Test-tags: areas=campaign risks=FLUXO,UI
// The unlink control of the campaign builder is the animated icon badge (Resources/js/campaign-connection-trash.js, makeButton + install):
// an icon, the label in the user's language (the title of the config) and an accessible name; the label opens on hover through the CSS.
// Runs the REAL script against a fake document; the look in the browser is checked in scripts/5/e2e/tests/campaign-connection-trash.spec.js.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const fs     = require('node:fs');

const SOURCE = fs.readFileSync(path.resolve(__dirname, '../../Resources/js/campaign-connection-trash.js'), 'utf8');

function el() {
    const e = {style: {}, children: [], attrs: {}, listeners: {}, className: '', textContent: ''};
    e.setAttribute = (k, v) => { e.attrs[k] = v; };
    e.appendChild = (c) => { e.children.push(c); c.parentNode = e; };
    e.addEventListener = (n, fn) => { e.listeners[n] = fn; };
    return e;
}

function setup(title) {
    const styles = [];
    const document = {createElement: () => el(), head: {appendChild: (s) => styles.push(s.textContent)}, addEventListener() {}};
    const root = {document};
    const circle = el();
    circle.parentNode = el();
    const line = {endpoints: [{}, {canvas: circle}]};
    const handlers = {};
    const inst = {
        getContainer: () => ({closest: () => null}),
        getAllConnections: () => [line],
        bind: (n, fn) => { (handlers[n] = handlers[n] || []).push(fn); },
        deleteConnection: (c) => { inst.deleted = c; },
    };
    const Mautic = {campaignBuilderInstance: inst, onPageLoad() {}, prepareCampaignCanvas() {}};
    vm.runInNewContext(SOURCE, root, {filename: 'campaign-connection-trash.js'});
    root.mauticPatchesConnectionTrashApi.install(Mautic, {title});
    const button = circle.parentNode.children[0];

    return {button, styles, inst, line};
}

test('the unlink control is an icon badge with the translated label, not a Bootstrap button', () => {
    const {button} = setup('Desconectar');
    assert.ok(button, 'a button is made for the line');
    assert.match(button.className, /(^|\s)mautic-patches-trash(\s|$)/);
    assert.doesNotMatch(button.className, /\bbtn\b/);
    assert.strictEqual(button.title, 'Desconectar');
    assert.strictEqual(button.attrs['aria-label'], 'Desconectar');
    const [icon, text] = button.children;
    assert.strictEqual(icon.className, 'ri-link-unlink');
    assert.strictEqual(icon.attrs['aria-hidden'], 'true');
    assert.strictEqual(text.className, 'mautic-patches-trash__text');
    assert.strictEqual(text.textContent, 'Desconectar');
});

test('the label follows the language of the config', () => {
    assert.strictEqual(setup('Disconnect').button.children[1].textContent, 'Disconnect');
});

test('the badge starts hidden, and the label opens only on hover', () => {
    const {button, styles} = setup('Desconectar');
    assert.strictEqual(button.style.cssText.includes('display:none'), true);
    const css = styles.join('');
    assert.match(css, /\.mautic-patches-trash\{[^}]*max-width:29px/);
    assert.match(css, /\.mautic-patches-trash:hover\{[^}]*max-width:160px/);
    assert.match(css, /\.mautic-patches-trash__text\{[^}]*opacity:0/);
    assert.match(css, /\.mautic-patches-trash:hover \.mautic-patches-trash__text\{opacity:1\}/);
});

test('clicking the badge unlinks that line', () => {
    const {button, inst, line} = setup('Desconectar');
    let prevented = false;
    button.listeners.click({preventDefault() { prevented = true; }, stopPropagation() {}});
    assert.strictEqual(inst.deleted, line);
    assert.ok(prevented);
});
