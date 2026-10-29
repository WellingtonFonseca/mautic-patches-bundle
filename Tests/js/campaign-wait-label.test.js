// Runs the REAL script that CampaignBuilderWaitLabelSubscriber puts on every admin page (it replaces core's
// Mautic.campaignBuilderUpdateLabel: the wait goes in a strip on the node) against a fake jsPlumb connection whose getOverlays() returns an object keyed by id,
// like jsPlumb 2.15.6 does.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const fs     = require('node:fs');
const {execFileSync} = require('node:child_process');

const WORDS = {
    hour: '%hour%h', hour_minutes: '%hour%h%minutes%m', at: 'às %time%', window: '%start% às %end%', range: '%from%–%to%',
    day_0: 'Dom', day_1: 'Seg', day_2: 'Ter', day_3: 'Qua', day_4: 'Qui', day_5: 'Sex', day_6: 'Sáb',
};

// the formatter of the lines, loaded on the fake page with the words of the language
function loadFormatter(win) {
    vm.runInNewContext(`${fs.readFileSync(path.resolve(__dirname, '../../Resources/js/campaign-node-schedule.js'), 'utf8')}; window.mauticPatchesScheduleApi.configure(${JSON.stringify(WORDS)});`, {window: win});
}

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script(rewrites = []) {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignBuilderWaitLabelSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignBuilderWaitLabelSubscriber::script(json_decode(${JSON.stringify(JSON.stringify(rewrites))}, true));`,
    ], {encoding: 'utf8'});
}

// A connection like jsPlumb 2.15.6: overlays kept in an object by id; removeOverlay deletes the entry.
function connection(overlays) {
    return {
        sourceId: 'src', targetId: 'CampaignEvent_1', overlays: {...overlays},
        getOverlays() { return this.overlays; },
        removeOverlay(id) { delete this.overlays[id]; },
        addOverlay() { throw new Error('no balloon must be drawn on the line'); },
    };
}

// A node: .campaign-event-type holding a first child (the card); a strip is created with createElement and inserted before it.
function fakeNode() {
    const children = [{className: 'campaign-event-content'}];
    const type = {
        children,
        querySelector(sel) { return sel.includes('campaign-event-wait') ? children.find((c) => c.className === 'campaign-event-wait') || null : null; },
        get firstChild() { return children[0]; },
        insertBefore(el, ref) { children.splice(children.indexOf(ref), 0, el); },
    };
    const node = {querySelector: () => type, getAttribute: () => 'action'};

    return {node, type};
}

// An element of the page. The strip gets its first line (hourglass + the wait text) when innerHTML is set; the lines of the schedule are
// added with appendChild. querySelector(':scope > .x') looks at the children only, querySelector('.x') at all the descendants.
// remove() takes the element out of the list that holds it (the card's .campaign-event-type for a strip, its parent otherwise).
function makeElement(root) {
    const el = {
        className: '', children: [], attrs: {}, textContent: '', parent: null,
        setAttribute(k, v) { el.attrs[k] = v; },
        getAttribute(k) { return Object.prototype.hasOwnProperty.call(el.attrs, k) ? el.attrs[k] : null; },
        appendChild(child) { child.parent = el; el.children.push(child); },
        querySelector(sel) {
            const name = sel.split('.').pop();
            const direct = sel.startsWith(':scope >');
            const search = (list) => {
                for (const c of list) {
                    if (c.className.split(' ').includes(name)) { return c; }
                    const found = direct ? null : search(c.children);
                    if (found) { return found; }
                }
                return null;
            };

            return search(el.children);
        },
        remove() { const list = el.parent ? el.parent.children : root.children; list.splice(list.indexOf(el), 1); },
    };
    Object.defineProperty(el, 'innerHTML', {set() {
        const line = makeElement(root);
        line.className = 'campaign-event-wait-line';
        const icon = makeElement(root);
        icon.className = 'ri-hourglass-line';
        const text = makeElement(root);
        text.className = 'campaign-event-wait-text';
        line.appendChild(icon);
        line.appendChild(text);
        el.appendChild(line);
    }});

    return el;
}

// the text of the wait in a strip, and the lines of the schedule under it as 'icon text'
const waitText = (strip) => strip.querySelector('.campaign-event-wait-text').textContent;
const extraLines = (strip) => {
    const extra = strip.querySelector(':scope > .campaign-event-wait-extra');

    return extra ? extra.children.map((row) => `${row.children[0].className} ${row.children[1].textContent}`) : [];
};

function load(conn, labels, node, rewrites = [], extra = {}) {
    const revalidated = [];
    const Mautic = {
        campaignBuilderLabels: labels,
        campaignBuilderInstance: {select: () => ({each: (fn) => [conn].forEach(fn)}), revalidate: (n) => revalidated.push(n)},
        campaignBuilderUpdateLabel() { throw new Error('core version must have been replaced'); },
        onPageLoad() { return 'core'; },
        ...extra,
    };
    const win = {};
    if (extra.campaignBuilderCanvasEvents || extra.campaignEventOnLoad) { loadFormatter(win); }
    const ready = [];
    const frames = [];
    const flush = () => { while (frames.length) frames.shift()(); };
    const document = {
        head: {appendChild() {}},
        createElement() { return makeElement(node.type); },
        getElementById: () => node.node,
    };
    vm.runInNewContext(script(rewrites), {Mautic, window: win, document, mQuery: (fn) => ready.push(fn), requestAnimationFrame: (fn) => frames.push(fn)});
    ready.forEach((fn) => fn());

    return {Mautic, revalidated, flush};
}

const strips = (node) => node.type.children.filter((c) => c.className === 'campaign-event-wait');

test('the balloon of the line is removed (overlays as an object) and nothing is drawn on the line', () => {
    const conn = connection({
        'src_CampaignEvent_1_connectionLabel': {type: 'Label', id: 'src_CampaignEvent_1_connectionLabel'},
        arrow: {type: 'Arrow', id: 'arrow'},
    });
    const n = fakeNode();
    const {Mautic} = load(conn, {CampaignEvent_1: 'esperar 2 dias'}, n);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.deepStrictEqual(Object.keys(conn.overlays), ['arrow']);
});

test('overlays given as an array are handled too', () => {
    const conn = connection({});
    conn.getOverlays = () => [{type: 'Label', id: 'a'}];
    let removed = null;
    conn.removeOverlay = (id) => { removed = id; };
    const {Mautic} = load(conn, {}, fakeNode());

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(removed, 'a');
});

test('the wait goes in one strip, before the card, and is reused when it changes', () => {
    const n = fakeNode();
    const {Mautic, revalidated, flush} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(n.type.children[0].className, 'campaign-event-wait');
    assert.strictEqual(waitText(n.type.children[0]), 'esperar 2 dias');

    Mautic.campaignBuilderLabels.CampaignEvent_1 = 'esperar 3 dias';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(waitText(strips(n)[0]), 'esperar 3 dias');
    assert.strictEqual(revalidated.length, 2);

    // the same text again (core calls the function once per connection when the canvas opens): nothing to redo
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(revalidated.length, 2);
});

test('many nodes changed in one moment are revalidated together, once each, on the next frame', () => {
    const a = fakeNode();
    const {Mautic, revalidated, flush} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias', CampaignEvent_2: 'esperar 2 dias'}, a);
    // the fake document answers every id with the same node: three updates, one real change
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_2');
    Mautic.campaignBuilderLabels.CampaignEvent_1 = 'esperar 5 dias';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(revalidated.length, 0, 'nothing is laid out before the frame');
    flush();
    assert.strictEqual(revalidated.length, 1, 'the same node is revalidated once');
});

test('the helper of the Preview tab puts the strip on a given node', () => {
    const n = fakeNode();
    const ctx = {Mautic: {campaignBuilderLabels: {}, campaignBuilderInstance: null, onPageLoad() {}}};
    const document = {head: {appendChild() {}}, createElement() { return makeElement(n.type); }, getElementById: () => null};
    const window = {};
    vm.runInNewContext(script(), {...ctx, window, document, mQuery: () => {}});

    window.mauticPatchesSetWait(n.node, 'esperar 1 minuto');

    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(waitText(strips(n)[0]), 'esperar 1 minuto');
});

test('no wait: the strip is removed', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n);
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    Mautic.campaignBuilderLabels.CampaignEvent_1 = '';
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n).length, 0);
});

test('a source (no .campaign-event-type) is left alone', () => {
    const n = {node: {querySelector: () => null, getAttribute: () => 'source'}, type: {children: []}};
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'x'}, n);

    assert.doesNotThrow(() => Mautic.campaignBuilderUpdateLabel('CampaignEvent_1'));
});

test('the page load of core still runs, and the patch holds after it', () => {
    const {Mautic} = load(connection({}), {}, fakeNode());

    assert.strictEqual(Mautic.onPageLoad(), 'core');
    assert.doesNotThrow(() => Mautic.campaignBuilderUpdateLabel('CampaignEvent_1'));
});

test('the texts of the Preview tab are put on the nodes now and after every Mautic page load, and a new campaign replaces them', () => {
    const preview = fakeNode();
    const win = {};
    const document = {head: {appendChild() {}}, createElement() { return makeElement(preview.type); },
        getElementById: () => null, querySelector: (sel) => (sel === '#preview-container #CampaignEvent_172' ? preview.node : null)};
    const Mautic = {campaignBuilderLabels: {}, campaignBuilderInstance: null, onPageLoad() { return 'core'; }};
    vm.runInNewContext(script(), {Mautic, window: win, document, mQuery: () => {}});

    win.mauticPatchesPreviewWaits = {CampaignEvent_172: 'esperar 1 minuto'};
    // the node is not in the page yet (ajax): nothing to do, nothing breaks
    assert.doesNotThrow(() => win.mauticPatchesApplyWaits());
    assert.strictEqual(strips(preview).length, 1, 'now');

    // a Mautic page load applies them again: unchanged, still one strip
    assert.strictEqual(Mautic.onPageLoad(), 'core');
    assert.strictEqual(strips(preview).length, 1);
    assert.strictEqual(waitText(strips(preview)[0]), 'esperar 1 minuto');
});

const WINDOW_EVENT = {triggerMode: 'interval', triggerRestrictedStartHour: {date: '1970-01-01 10:00:00.000000'}, triggerRestrictedStopHour: '16:20', triggerRestrictedDaysOfWeek: [1, 3, 4]};
const HOURS = 'ri-time-line 10h às 16h20m';
const DAYS = 'ri-calendar-line Seg, Qua, Qui';

test('the schedule is a line per fact under the wait text, in the same strip, and follows the edits', () => {
    const n = fakeNode();
    const {Mautic, revalidated, flush} = load(connection({}), {CampaignEvent_1: 'esperar 3 dias'}, n, [], {campaignBuilderCanvasEvents: {1: WINDOW_EVENT}});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(strips(n).length, 1);
    assert.strictEqual(waitText(strips(n)[0]), 'esperar 3 dias');
    assert.strictEqual(strips(n)[0].children[0].children[0].className, 'ri-hourglass-line', 'the wait line has the hourglass');
    assert.deepStrictEqual(extraLines(strips(n)[0]), [HOURS, DAYS]);
    assert.strictEqual(revalidated.length, 1);

    // the same schedule again: nothing to redo
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.strictEqual(revalidated.length, 1);

    // only the days: the hours line goes, the node is revalidated (it got shorter)
    Mautic.campaignBuilderCanvasEvents[1] = {triggerMode: 'interval', triggerRestrictedDaysOfWeek: [1, 3, 4]};
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.deepStrictEqual(extraLines(strips(n)[0]), [DAYS]);
    assert.strictEqual(revalidated.length, 2);

    // no restriction any more: the lines go, the wait stays
    Mautic.campaignBuilderCanvasEvents[1] = {triggerMode: 'interval'};
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    flush();
    assert.deepStrictEqual(extraLines(strips(n)[0]), []);
    assert.strictEqual(strips(n)[0].querySelector(':scope > .campaign-event-wait-extra'), null);
    assert.strictEqual(waitText(strips(n)[0]), 'esperar 3 dias');
    assert.strictEqual(revalidated.length, 3);
});

test('an event without a wait text has no strip and so no lines', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {}, n, [], {campaignBuilderCanvasEvents: {1: WINDOW_EVENT}});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(strips(n).length, 0);
});

test('an event the builder does not know has no lines, and the strip still works', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n, [], {campaignBuilderCanvasEvents: {}});

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.deepStrictEqual(extraLines(strips(n)[0]), []);
    assert.strictEqual(waitText(strips(n)[0]), 'esperar 2 dias');
});

test('without the formatter on the page the strip works and has no lines', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n, [], {});
    Mautic.campaignBuilderCanvasEvents = {1: WINDOW_EVENT};

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.deepStrictEqual(extraLines(strips(n)[0]), []);
});

test('after an edit core stores the event AFTER the label update: the lines are read again once core is done', () => {
    const n = fakeNode();
    const order = [];
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 3 dias'}, n, [], {
        campaignBuilderCanvasEvents: {1: WINDOW_EVENT},
        campaignEventOnLoad(container, response) {
            // what core does: the label first, the stored event last
            order.push('label');
            Mautic.campaignBuilderUpdateLabel('CampaignEvent_' + response.eventId);
            order.push('store');
            Mautic.campaignBuilderCanvasEvents[response.event.id] = response.event;

            return 'core-result';
        },
    });
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    assert.deepStrictEqual(extraLines(strips(n)[0]), [HOURS, DAYS]);

    const edited = {id: 1, triggerMode: 'interval', triggerHour: '09:30', triggerRestrictedDaysOfWeek: []};
    const result = Mautic.campaignEventOnLoad('c', {eventId: 1, event: edited});

    assert.strictEqual(result, 'core-result');
    assert.deepStrictEqual(order, ['label', 'store']);
    assert.deepStrictEqual(extraLines(strips(n)[0]), ['ri-time-line às 9h30m']);
});

test('opening the form (no eventId), or a deleted event, does not touch the strip', () => {
    const n = fakeNode();
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 3 dias'}, n, [], {
        campaignBuilderCanvasEvents: {1: WINDOW_EVENT},
        campaignEventOnLoad() { return 'core'; },
    });
    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');
    Mautic.campaignBuilderCanvasEvents[1] = {triggerMode: 'interval'};

    assert.strictEqual(Mautic.campaignEventOnLoad('c', {}), 'core');
    assert.strictEqual(Mautic.campaignEventOnLoad('c', {eventId: 1, deleted: true, event: {id: 1}}), 'core');
    assert.deepStrictEqual(extraLines(strips(n)[0]), [HOURS, DAYS], 'still the old lines: nothing was refreshed');
});

test('the edit hook is installed once, however many page loads', () => {
    const {Mautic} = load(connection({}), {}, fakeNode(), [], {campaignBuilderCanvasEvents: {}, campaignEventOnLoad() { return 'core'; }});
    const first = Mautic.campaignEventOnLoad;

    Mautic.onPageLoad();
    Mautic.onPageLoad();

    assert.strictEqual(Mautic.campaignEventOnLoad, first);
});

test('the Preview tab: the schedules left by the page are put on the nodes with the texts', () => {
    const preview = fakeNode();
    const win = {};
    loadFormatter(win);
    const document = {head: {appendChild() {}}, createElement() { return makeElement(preview.type); }, getElementById: () => null,
        querySelector: (sel) => (sel === '#preview-container #CampaignEvent_172' ? preview.node : null)};
    vm.runInNewContext(script(), {Mautic: {campaignBuilderLabels: {}, campaignBuilderInstance: null, onPageLoad() {}}, window: win, document, mQuery: () => {}});

    win.mauticPatchesPreviewWaits = {CampaignEvent_172: 'esperar 1 dia'};
    win.mauticPatchesPreviewSchedules = {CampaignEvent_172: WINDOW_EVENT};
    win.mauticPatchesApplyWaits();

    assert.deepStrictEqual(extraLines(strips(preview)[0]), [HOURS, DAYS]);

    // the next campaign has no schedule: the lines go
    win.mauticPatchesPreviewSchedules = {};
    win.mauticPatchesApplyWaits();
    assert.deepStrictEqual(extraLines(strips(preview)[0]), []);
});

const REWRITES = [
    {from: 'entre %number% %unit%', to: 'esperar %number% %unit%'},
    {from: 'por %full%', to: 'esperar até %full%'},
];

test('after a condition the inaction text ("entre 1 dia") is turned back into the plain one', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'entre 1 dia'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(waitText(strips(n)[0]), 'esperar 1 dia');
});

test('the date text of the no path after a condition is turned back too', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'por 10 de outubro de 2026 às 09:00'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(waitText(strips(n)[0]), 'esperar até 10 de outubro de 2026 às 09:00');
});

test('after a decision the inaction text stays: it is a deadline there', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'decision';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'entre 1 dia'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(waitText(strips(n)[0]), 'entre 1 dia');
});

test('a text that is not the inaction one is left alone after a condition', () => {
    const n = fakeNode();
    n.node.getAttribute = () => 'condition';
    const {Mautic} = load(connection({}), {CampaignEvent_1: 'esperar 2 dias'}, n, REWRITES);

    Mautic.campaignBuilderUpdateLabel('CampaignEvent_1');

    assert.strictEqual(waitText(strips(n)[0]), 'esperar 2 dias');
});
