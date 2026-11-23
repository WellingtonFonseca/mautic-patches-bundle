// Test-tags: areas=campaign risks=UI,FLUXO
// The badges of the segment and campaign details pages (Resources/js/segment-campaign-detail-badges.js): which state the marker tells and what is
// written in the toolbar. The look is checked in the browser (scripts/5/e2e/tests/segment-campaign-detail-status.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/segment-campaign-detail-badges.js');

const cfg = {active: 'Ativo', inactive: 'Inativo', restart: 'Reentrada', global: 'Global', filters: 'Filtros', rebuild: 'Sem atualizar há %count% h', slow: 'Montagem lenta: %count% s'};

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
        querySelector(selector) { return selector === '.patches-details-badges' ? this.children.find((c) => c.className === 'patches-details-badges') || null : null; }
    };
}

/** A details page: a toolbar with the Editar button and, when `marker` is given, the hidden marker of the subscriber. */
function page(marker, path = '/s/campaigns/view/7', readyState = 'complete') {
    const bar = node('div'), edit = node('a');
    bar.appendChild(edit);
    const markerNode = marker ? {getAttribute: (k) => marker[k]} : null;
    const doc = {
        readyState,
        location: {pathname: path},
        querySelector: (s) => {
            if (s === '#toolbar .std-toolbar') { return bar; }

            return s === '.patches-detail-status' ? markerNode : null;
        },
        createElement: (tag) => node(tag)
    };

    return {doc, bar, edit};
}

test('a details page is /s/segments/view/{id} or /s/campaigns/view/{id}, under a subdirectory or index.php too', () => {
    assert.ok(api.isDetailsPath('/s/segments/view/5'));
    assert.ok(api.isDetailsPath('/index.php/s/campaigns/view/12'));
    assert.ok(api.isDetailsPath('/mautic/s/campaigns/view/3'));
    assert.ok(!api.isDetailsPath('/s/campaigns/edit/5'));
    assert.ok(!api.isDetailsPath('/s/campaigns/view/5/extra'));
    assert.ok(!api.isDetailsPath('/s/contacts/view/7'));
    assert.ok(!api.isDetailsPath('/s/campaigns'));
});

test('the marker tells the status and the reentry', () => {
    assert.deepStrictEqual(api.readMarker(page({'data-status': 'published', 'data-restart': '1'}).doc), {status: 'published', restart: true, flags: [], rebuild: '', slow: '', segment: null});
    assert.deepStrictEqual(api.readMarker(page({'data-status': 'unpublished'}).doc), {status: 'unpublished', restart: false, flags: [], rebuild: '', slow: '', segment: null});
    assert.strictEqual(api.readMarker(page(null).doc), null);
});

test('any status that is not "published" is inactive', () => {
    assert.strictEqual(api.readMarker(page({'data-status': 'pending'}).doc).status, 'unpublished');
});

test('an active page has the green status badge with the play icon; an inactive one the red badge with the stop icon', () => {
    assert.deepStrictEqual(api.badgesOf({status: 'published', restart: false, flags: []}, cfg), [{channel: 'status', icon: 'ri-play-fill', text: 'Ativo', modifier: 'production'}]);
    assert.deepStrictEqual(api.badgesOf({status: 'unpublished', restart: false, flags: []}, cfg), [{channel: 'status', icon: 'ri-stop-fill', text: 'Inativo', modifier: 'failed'}]);
});

test('the reentry badge comes after the status one, only when the campaign lets contacts enter again', () => {
    assert.deepStrictEqual(api.badgesOf({status: 'published', restart: true, flags: []}, cfg).map((b) => b.channel), ['status', 'restart']);
    assert.strictEqual(api.badgesOf({status: 'published', restart: false, flags: []}, cfg).length, 1);
});

test('the box is the first thing of the toolbar, with the status badge and then the reentry one', () => {
    const p = page({'data-status': 'unpublished', 'data-restart': '1'});
    assert.strictEqual(api.addDetailBadges(p.doc, cfg), true);
    const box = p.bar.children[0];
    assert.strictEqual(box.className, 'patches-details-badges');
    assert.strictEqual(p.bar.children[1], p.edit);
    assert.deepStrictEqual(box.children.map((b) => b.attrs.title), ['Inativo', 'Reentrada']);
    assert.ok(box.children[0].className.indexOf('patches-badge--failed') > -1);
    assert.ok(box.children[1].className.indexOf('patches-restart-badge') > -1);
});

test('a box that is already right is not written again (the observer would loop)', () => {
    const p = page({'data-status': 'published'});
    assert.strictEqual(api.addDetailBadges(p.doc, cfg), true);
    const box = p.bar.children[0];
    assert.strictEqual(api.addDetailBadges(p.doc, cfg), false);
    assert.strictEqual(p.bar.children[0], box);
    assert.strictEqual(p.bar.children.length, 2);
});

test('a changed state replaces the box', () => {
    const marker = {'data-status': 'published'};
    const p = page(marker);
    api.addDetailBadges(p.doc, cfg);
    marker['data-status'] = 'unpublished';
    assert.strictEqual(api.addDetailBadges(p.doc, cfg), true);
    assert.strictEqual(p.bar.children.length, 2);
    assert.strictEqual(p.bar.children[0].children[0].attrs.title, 'Inativo');
});

test('nothing is written without the marker, on another page, or while the page is still being parsed', () => {
    const none = page(null);
    assert.strictEqual(api.addDetailBadges(none.doc, cfg), false);
    const edit = page({'data-status': 'published'}, '/s/campaigns/edit/7');
    assert.strictEqual(api.addDetailBadges(edit.doc, cfg), false);
    const loading = page({'data-status': 'published'}, '/s/campaigns/view/7', 'loading');
    assert.strictEqual(api.addDetailBadges(loading.doc, cfg), false);
    assert.strictEqual(loading.bar.children.length, 1);
});

test('a change of the reentry alone (same status) replaces the box', () => {
    const marker = {'data-status': 'published'};
    const p = page(marker);
    api.addDetailBadges(p.doc, cfg);
    marker['data-restart'] = '1';
    assert.strictEqual(api.addDetailBadges(p.doc, cfg), true);
    assert.deepStrictEqual(p.bar.children[0].children.map((b) => b.attrs.title), ['Ativo', 'Reentrada']);
});

// the install: a fake window with the page, a MutationObserver that only records, and a frame that runs at once
function installedWindow() {
    require('../../Resources/js/list-status-toggle.js'); // loaded without a window, as the details script needs it
    const p = page({'data-status': 'published'});
    const record = {observed: [], observers: 0, listeners: [], styles: 0, frames: []};
    p.doc.head = {appendChild() { record.styles++; }};
    p.doc.body = {};
    p.doc.addEventListener = (name) => { record.listeners.push(name); };
    p.doc.createElement = (tag) => node(tag);
    let callback = null;
    const win = {
        document: p.doc,
        MutationObserver: function (cb) { record.observers++; callback = cb; this.observe = (target, options) => record.observed.push(options); },
        requestAnimationFrame: (fn) => { record.frames.push(fn); }
    };
    const saved = global.window;
    delete require.cache[require.resolve('../../Resources/js/segment-campaign-detail-badges.js')];
    global.window = win;
    const mod = require('../../Resources/js/segment-campaign-detail-badges.js');
    global.window = saved;

    return {p, win, record, mod, change: () => callback()};
}

test('install draws at once, listens to DOMContentLoaded and to the changes of the page (ajax), and adds the style', () => {
    const w = installedWindow();
    w.mod.installSegmentCampaignDetail(null, {}, Object.assign({style: '.x{}'}, cfg));
    assert.strictEqual(w.p.bar.children[0].className, 'patches-details-badges');
    assert.deepStrictEqual(w.record.listeners, ['DOMContentLoaded']);
    assert.deepStrictEqual(w.record.observed, [{childList: true, subtree: true}]);
    assert.strictEqual(w.record.styles, 1);
});

test('install runs once: a second call (the script comes with every page) changes nothing', () => {
    const w = installedWindow();
    w.mod.installSegmentCampaignDetail(null, {}, Object.assign({style: '.x{}'}, cfg));
    w.mod.installSegmentCampaignDetail(null, {}, Object.assign({style: '.x{}'}, cfg));
    assert.strictEqual(w.record.observers, 1);
    assert.deepStrictEqual(w.record.listeners, ['DOMContentLoaded']);
    assert.strictEqual(w.record.styles, 1);
});

test('the style of the same CSS is added once, even if another install asks for it', () => {
    const w = installedWindow();
    w.win.mauticPatchesStyles = {'.x{}': true};
    w.mod.installSegmentCampaignDetail(null, {}, Object.assign({style: '.x{}'}, cfg));
    assert.strictEqual(w.record.styles, 0);
});

test('a change of the page draws again, once per frame', () => {
    const w = installedWindow();
    w.mod.installSegmentCampaignDetail(null, {}, Object.assign({style: '.x{}'}, cfg));
    w.p.bar.children.shift(); // the page was replaced by ajax: the box is gone
    w.change();
    w.change();
    assert.strictEqual(w.record.frames.length, 1, 'two changes in a frame ask for one drawing');
    w.record.frames[0]();
    assert.strictEqual(w.p.bar.children[0].className, 'patches-details-badges');
    w.change();
    assert.strictEqual(w.record.frames.length, 2, 'the next frame can ask again');
});

test('the marker of a segment lists its flags and the numbers of the two warnings', () => {
    const m = api.readMarker(page({'data-status': 'published', 'data-flags': 'global,filters,rebuild,slow', 'data-rebuild-hours': '72', 'data-build-seconds': '41'}).doc);

    assert.deepStrictEqual(m.flags, ['global', 'filters', 'rebuild', 'slow']);
    assert.strictEqual(m.rebuild, '72');
    assert.strictEqual(m.slow, '41');
});

test('the flags come after the status, in the order of the list, with the count in the warning text', () => {
    const badges = api.badgesOf({status: 'published', restart: false, flags: ['slow', 'global', 'rebuild', 'filters'], rebuild: '72', slow: '41'}, cfg);

    assert.deepStrictEqual(badges.map((b) => b.text), ['Ativo', 'Global', 'Filtros', 'Sem atualizar há 72 h', 'Montagem lenta: 41 s']);
    assert.deepStrictEqual(badges.map((b) => b.icon).slice(1), ['ri-global-line', 'ri-filter-2-fill', 'ri-error-warning-line', 'ri-time-line']);
    assert.deepStrictEqual(badges.map((b) => b.modifier).slice(1), ['flag', 'flag', 'failed', 'failed']);
});

test('a flag the marker does not list gets no badge', () => {
    assert.strictEqual(api.badgesOf({status: 'published', restart: false, flags: ['filters']}, cfg).length, 2);
});

test('a change of the flags alone draws the box again', () => {
    const p = page({'data-status': 'published', 'data-flags': 'global'}, '/s/segments/view/3');
    api.addDetailBadges(p.doc, cfg);
    const first = p.bar.children[0];
    p.doc.querySelector = (s) => (s === '.patches-segment-header' ? null : s === '#toolbar .std-toolbar' ? p.bar : {getAttribute: (k) => ({'data-status': 'published', 'data-flags': 'global,filters'})[k]});

    assert.ok(api.addDetailBadges(p.doc, cfg));
    assert.notStrictEqual(p.bar.children[0], first);
    assert.strictEqual(p.bar.children[0].children.length, 3);
});

// WEL-77: the contacts badge and the "Updated on" line of the segment page
const segMarker = (extra = {}) => Object.assign({
    'data-status': 'published', 'data-segment-id': '9', 'data-count': '6', 'data-count-text': '6 contatos', 'data-count-url': '/s/contacts?search=segment:x',
    'data-built-text': 'Atualizado em 01/01/2026', 'data-updating-text': 'Atualizando'
}, extra);

/** A segment page whose toolbar sits in a .page-header, with a recorder of what the script puts in the header. */
function segmentPage(marker) {
    const p = page(marker, '/s/segments/view/9');
    const header = node('div');
    header.appendChild(p.bar);
    p.bar.closest = (s) => (s === '.page-header' ? header : null);
    const base = p.doc.querySelector;
    p.doc.querySelector = (s) => (s === '.patches-segment-updated' ? header.children.find((c) => c.className.indexOf('patches-segment-updated') > -1) || null : base(s));

    return Object.assign(p, {header});
}

test('the marker of a segment carries the count, the link and the two texts; a campaign has none', () => {
    const seg = api.readMarker(segmentPage(segMarker()).doc).segment;
    assert.deepStrictEqual(seg, {id: '9', count: 6, countText: '6 contatos', countUrl: '/s/contacts?search=segment:x', builtText: 'Atualizado em 01/01/2026', updatingText: 'Atualizando'});
    assert.strictEqual(api.readMarker(page({'data-status': 'published'}).doc).segment, null);
});

test('the contacts badge is the first of the box, a link to the contacts of the segment; blue with contacts, gray when empty', () => {
    const p = segmentPage(segMarker());
    api.addDetailBadges(p.doc, cfg);
    const box = p.bar.children[0], badge = box.children[0];
    assert.strictEqual(badge.textContent, '6 contatos');
    assert.strictEqual(badge.attrs.href, '/s/contacts?search=segment:x');
    assert.ok(badge.className.indexOf('patches-count-badge') > -1 && badge.className.indexOf('patches-badge--info') > -1);
    assert.strictEqual(badge.attrs['data-segment-id'], '9');
    assert.strictEqual(box.children[1].attrs.title, 'Ativo');

    const empty = segmentPage(segMarker({'data-count': '0', 'data-count-text': '0 contatos'}));
    api.addDetailBadges(empty.doc, cfg);
    assert.ok(empty.bar.children[0].children[0].className.indexOf('patches-badge--neutral') > -1);
});

test('a campaign page gets neither the contacts badge nor the line', () => {
    const p = segmentPage({'data-status': 'published'});
    api.addDetailBadges(p.doc, cfg);
    assert.strictEqual(p.bar.children[0].children.length, 1);
    assert.strictEqual(p.header.children.length, 1);
});

test('a segment page: the header component takes the place of core heading and the document title follows', () => {
    const {doc, host, h1} = modalHead(segMarker());
    h1.textContent = 'ABC - Segment';
    const parent = node('div'), core = node('h1');
    core.textContent = 'Segment';
    parent.appendChild(core);
    host.contains = (n) => n === h1;
    host.setAttribute('data-placed-check', '');
    const all = [core, h1];
    doc.location = {pathname: '/s/segments/view/9'};
    doc.readyState = 'complete';
    doc.title = 'Segment | Mautic';
    const base = doc.querySelector;
    doc.querySelector = (s) => (s === '.patches-segment-header:not([data-placed]):not(.mautic-patches-segment-details *)' || s === '.patches-segment-header' ? host : base(s));
    doc.querySelectorAll = () => all;
    parent.replaceChild = function (a, b) { this.children[this.children.indexOf(b)] = a; };
    assert.strictEqual(api.addDetailBadges(doc, cfg), true);
    assert.strictEqual(parent.children[0], host);
    assert.strictEqual(host.attrs['data-placed'], '1');
    assert.strictEqual(doc.title, 'ABC - Segment | Mautic');
    assert.strictEqual(host.children[1].className, 'patches-details-badges');
});

// WEL-101: the header component (Segment/header.html.twig) of the details modal: the twig prints the title and the "Updated on" line, the script puts the badges after the title
function modalHead(marker) {
    const markerNode = {getAttribute: (k) => marker[k]};
    const host = node('div');
    const h1 = node('h1');
    h1.className = 'page-header-title patches-modal-name';
    const line = node('div');
    line.className = 'patches-segment-updated text-muted';
    host.appendChild(h1);
    host.appendChild(line);
    h1.nextSibling = line;
    const find = (s) => {
        const box = host.children.find((c) => c.className === 'patches-details-badges');
        if (s === '.patches-details-badges') { return box || null; }

        return s === '.patches-count-badge' && box ? box.children.find((c) => c.className.indexOf('patches-count-badge') > -1) || null : null;
    };
    host.querySelector = (s) => (s === '.patches-detail-status' ? markerNode : s === '.patches-modal-name' ? h1 : find(s));
    const doc = {
        querySelector: (s) => (s === '.patches-segment-header' ? host : null),
        createElement: (tag) => {
            const n = node(tag);
            n.listeners = {};
            n.addEventListener = (type, fn) => { n.listeners[type] = fn; };

            return n;
        }
    };

    return {doc, host, h1, line};
}

test('the modal header keeps the title and the line of the twig and puts the badges between them', () => {
    const {doc, host, h1, line} = modalHead(segMarker());
    assert.strictEqual(api.renderModalHead(doc, cfg), true);
    assert.strictEqual(host.children.length, 3);
    assert.strictEqual(host.children[0], h1);
    assert.strictEqual(host.children[2], line);
    const box = host.children[1];
    assert.strictEqual(box.className, 'patches-details-badges');
    assert.strictEqual(box.children[0].textContent, '6 contatos');
    assert.strictEqual(box.children[1].attrs.title, 'Ativo');
});

test('the header is drawn once; a campaign marker gets its badges too; no host draws nothing', () => {
    const {doc, host} = modalHead(segMarker());
    api.renderModalHead(doc, cfg);
    api.renderModalHead(doc, cfg);
    assert.strictEqual(host.children.length, 3);

    const campaign = modalHead({'data-status': 'published'});
    api.renderModalHead(campaign.doc, cfg);
    assert.strictEqual(campaign.host.children.length, 3);
    assert.strictEqual(campaign.host.children[1].children[0].attrs.title, 'Ativo');
    assert.strictEqual(api.renderModalHead({querySelector: () => null}, cfg), false);
});

test('the contacts badge of the modal header links to the contacts and listens to the click (that closes the modal, it leaves the page)', () => {
    const {doc, host} = modalHead(segMarker());
    api.renderModalHead(doc, cfg);
    const badge = host.children[1].children[0];
    assert.strictEqual(badge.attrs.href, '/s/contacts?search=segment:x');
    assert.strictEqual(typeof badge.listeners.click, 'function');
    assert.doesNotThrow(() => badge.listeners.click()); // no mQuery in node: must not throw
});
