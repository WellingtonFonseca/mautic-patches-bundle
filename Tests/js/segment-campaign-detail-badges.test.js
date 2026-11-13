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
    assert.deepStrictEqual(api.readMarker(page({'data-status': 'published', 'data-restart': '1'}).doc), {status: 'published', restart: true, flags: [], rebuild: '', slow: ''});
    assert.deepStrictEqual(api.readMarker(page({'data-status': 'unpublished'}).doc), {status: 'unpublished', restart: false, flags: [], rebuild: '', slow: ''});
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
    p.doc.querySelector = (s) => (s === '#toolbar .std-toolbar' ? p.bar : {getAttribute: (k) => ({'data-status': 'published', 'data-flags': 'global,filters'})[k]});

    assert.ok(api.addDetailBadges(p.doc, cfg));
    assert.notStrictEqual(p.bar.children[0], first);
    assert.strictEqual(p.bar.children[0].children.length, 3);
});
