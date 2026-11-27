// Test-tags: areas=campaign risks=FLUXO,UI
// The green "Conectar" badge below the dots of a node (Resources/js/campaign-connection-trash.js, decorateConnect) and the builder button
// label. Runs the REAL script against a fake document. Rules: the badge is made only for the anchors below a node; it carries the
// translated label; the dot stays draggable (its mousedown is not touched), its click is swallowed and only the badge click fires the
// endpoint click; the badge does not show while the event panel is open.
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const fs     = require('node:fs');

const SOURCE = fs.readFileSync(path.resolve(__dirname, '../../Resources/js/campaign-connection-trash.js'), 'utf8');

function el() {
    const e = {style: {}, children: [], attrs: {}, listeners: {}, className: '', textContent: '', offsetLeft: 100, offsetTop: 50, offsetWidth: 20, offsetHeight: 20, childNodes: []};
    e.setAttribute = (k, v) => { e.attrs[k] = v; };
    e.getAttribute = (k) => e.attrs[k];
    e.appendChild = (c) => { e.children.push(c); c.parentNode = e; e.firstChild = e.children[0]; };
    e.addEventListener = (n, fn, capture) => { (e.listeners[n] = e.listeners[n] || []).push({fn, capture}); };
    return e;
}

function setup(anchorName, {panelOpen = false} = {}) {
    const circle = el();
    circle.parentNode = el();
    const fired = [];
    const endpoint = {canvas: circle, fire: (n, ep, ev) => fired.push({n, ev})};
    const panel = {classList: {contains: (c) => c === 'hide' && !panelOpen}};
    const textNode = {nodeType: 3, textContent: ' Abrir Construtor de Campanha '};
    const toolbar = el();
    toolbar.childNodes = [textNode];
    const document = {
        createElement: () => el(),
        head: {appendChild() {}},
        addEventListener() {},
        getElementById: (id) => (id === 'CampaignEventPanel' ? panel : id === 'campaign_buttons_builder_toolbar' ? toolbar : null),
        querySelectorAll: () => [{id: 'CampaignEvent_1'}],
    };
    const root = {document, setTimeout, clearTimeout, getComputedStyle: () => ({display: 'block'})};
    const inst = {
        getContainer: () => ({closest: () => null}),
        getAllConnections: () => [],
        getEndpoints: () => [endpoint],
        bind() {},
    };
    const Mautic = root.Mautic = {
        campaignBuilderInstance: inst,
        onPageLoad() {},
        prepareCampaignCanvas() {},
        campaignBuilderRegisterAnchors() {},
        campaignBuilderGetEndpointDetails: () => ({anchorName}),
    };
    vm.runInNewContext(SOURCE, root, {filename: 'campaign-connection-trash.js'});
    root.mauticPatchesConnectionTrashApi.install(Mautic, {title: 'Desconectar', connect: 'Conectar', builder: 'Construtor'});
    Mautic.prepareCampaignCanvas();
    Mautic.campaignBuilderRegisterAnchors(['bottom'], {});

    return {circle, fired, endpoint, textNode, toolbar};
}

test('a dot below a node gets a green badge with the translated label and a plus icon', () => {
    for (const anchor of ['bottom', 'yes', 'no', 'leadsource']) {
        const {circle} = setup(anchor);
        const badge = circle.parentNode.children[0];
        assert.ok(badge, `badge for ${anchor}`);
        assert.match(badge.className, /mautic-patches-connect/);
        assert.strictEqual(badge.title, 'Conectar');
        assert.strictEqual(badge.children[0].className, 'ri-add-line');
        assert.strictEqual(badge.children[1].textContent, 'Conectar');
    }
});

test('other anchors (top, left) get no connect badge', () => {
    const {circle, endpoint} = setup('top');
    assert.strictEqual(circle.parentNode.children.length, 0);
    assert.ok(!endpoint.mauticPatchesConnect);
});

test('the badge is made once per dot even when the anchors are registered again', () => {
    const {circle, endpoint} = setup('bottom');
    assert.strictEqual(circle.parentNode.children.length, 1);
    assert.strictEqual(endpoint.mauticPatchesConnect, true);
});

test('the dot click is swallowed in capture, the dot press is not touched (drag still connects), the badge click fires the endpoint click', () => {
    const {circle, fired} = setup('bottom');
    assert.ok(!circle.listeners.mousedown, 'no mousedown handler on the dot');
    const click = circle.listeners.click.find((l) => l.capture);
    assert.ok(click, 'capture click on the dot');
    let stopped = false;
    click.fn({stopImmediatePropagation() { stopped = true; }});
    assert.ok(stopped);
    assert.strictEqual(fired.length, 0, 'the dot click opens nothing');

    const badge = circle.parentNode.children[0];
    badge.listeners.click[0].fn({preventDefault() {}, stopPropagation() {}});
    assert.strictEqual(fired.length, 1);
    assert.strictEqual(fired[0].n, 'click');
    assert.strictEqual(fired[0].ev.target, circle);
});

test('hovering the dot shows the badge just below it; with the event panel open it stays hidden', () => {
    const open = setup('bottom');
    const shownBadge = open.circle.parentNode.children[0];
    shownBadge.style.display = 'none'; // what the browser reads out of the cssText of the badge
    open.circle.listeners.mouseenter[0].fn();
    assert.strictEqual(shownBadge.style.top, '72px');
    assert.notStrictEqual(shownBadge.style.display, 'none');

    const blocked = setup('bottom', {panelOpen: true});
    const hiddenBadge = blocked.circle.parentNode.children[0];
    hiddenBadge.style.display = 'none';
    blocked.circle.listeners.mouseenter[0].fn();
    assert.strictEqual(hiddenBadge.style.top, undefined);
    assert.strictEqual(hiddenBadge.style.display, 'none');
});

test('the builder button text is replaced by the plugin label', () => {
    const {textNode, toolbar} = setup('bottom');
    assert.strictEqual(textNode.textContent.trim(), 'Construtor');
    assert.strictEqual(toolbar.attrs['data-patches-label'], 'Construtor');
});
