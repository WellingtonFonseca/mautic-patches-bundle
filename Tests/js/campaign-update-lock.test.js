// Campaign kind of the lock script (CampaignUpdateLockSubscriber): done = the status says running:false. Runs the REAL script that SegmentUpdateLockSubscriber::script builds (taken from the PHP class, not copied) against a
// small fake browser: anchors and "x" lines, a fake mQuery, fake timers, and a recorder for the status requests.
// It covers what the PHP tests cannot: the click, the lock, the polling, the unlock and the reload actually happening in
// the order the user sees them.
//
// Run inside the Mautic container, no extra dependencies (Node's built-in runner):
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const POLL = 3000;
const MAX  = 120000;

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/SegmentUpdateLockSubscriber.php');

// The script exactly as the page gets it.
const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\SegmentUpdateLockSubscriber::script(${POLL}, ${MAX}, 'campaign');`,
], {encoding: 'utf8'});

// ---- fake DOM ---------------------------------------------------------------------------------------------------------

class Node {
    constructor(tag, attrs = {}, parent = null) {
        this.tag      = tag;
        this.attrs    = {...attrs};
        this.classes  = new Set((attrs.class || '').split(/\s+/).filter(Boolean));
        this.style    = {};
        this.store    = {};
        this.children = [];
        this.parent   = parent;
    }

    getAttribute(name) {
        return name in this.attrs ? this.attrs[name] : null;
    }

    matchesLink() {
        return this.tag === 'a' && (this.attrs.href || '').includes('/s/campaign-rebuild/');
    }

    closest(selector) {
        assert.ok(selector.startsWith('a[href*='), 'the script only asks for the rebuild link');

        for (let n = this; n; n = n.parent) {
            if (n.matchesLink()) {
                return n;
            }
        }

        return null;
    }
}

function wrap(nodes) {
    const w = {
        nodes,
        each(fn)     { nodes.forEach((n) => fn.call(n)); return w; },
        addClass(c)  { nodes.forEach((n) => n.classes.add(c)); return w; },
        attr(k, v)   { if (v === undefined) { return nodes[0] ? nodes[0].attrs[k] : undefined; } nodes.forEach((n) => { n.attrs[k] = v; }); return w; },
        css(k, v)    { nodes.forEach((n) => { n.style[k] = v; }); return w; },
        data(k, v)   {
            if (v === undefined) { const n = nodes[0]; return n && (k in n.store ? n.store[k] : n.attrs['data-' + k]); }
            nodes.forEach((n) => { n.store[k] = v; });
            return w;
        },
        empty()      { nodes.forEach((n) => { n.children = []; }); return w; },
        append(x)    { nodes.forEach((n) => n.children.push(...x.nodes)); return w; },
        text(t)      { nodes.forEach((n) => { n.children = [String(t)]; }); return w; },
    };

    return w;
}

// A fresh browser around one run of the script.
function boot({path: pathname = '/s/campaigns', search = ''} = {}) {
    let now     = 1000000;
    let seq     = 0;
    let timers  = [];
    const ajax  = [];
    const loads = [];
    const window = {location: {pathname, search}};
    let clickHandler = null;
    const page  = {links: [], lines: []};
    const pageLoads = [];

    const mQuery = (arg) => {
        if (typeof arg === 'string' && arg.startsWith('<')) {
            const tag   = /^<(\w+)/.exec(arg)[1];
            const klass = /class=\\?"([^"\\]*)/.exec(arg);

            const node = new Node(tag, klass ? {class: klass[1]} : {});
            node.html = arg;

            return wrap([node]);
        }

        if (typeof arg === 'string' && arg.startsWith('a[href*=')) {
            return wrap(page.links.slice());
        }

        if (arg === '.campaign-updating-line') {
            return wrap(page.lines.slice());
        }

        return wrap([arg]);
    };

    mQuery.ajax = (opts) => {
        const call = {opts, done: [], fail: []};
        ajax.push(call);
        const chain = {done(cb) { call.done.push(cb); return chain; }, fail(cb) { call.fail.push(cb); return chain; }};

        return chain;
    };

    const Mautic = {
        onPageLoad() { pageLoads.push('original'); return 'original-result'; },
        loadContent(url) { loads.push(url); },
    };

    const sandbox = {
        window,
        document: {addEventListener(type, fn, capture) { if (type === 'click') { clickHandler = {fn, capture}; } }},
        mQuery,
        Mautic,
        Date: {now: () => now},
        setTimeout: (fn, ms) => { timers.push({at: now + (ms || 0), fn, id: seq++}); return seq; },
    };

    vm.runInNewContext(SCRIPT, sandbox);

    const env = {
        ajax, loads, window, Mautic, page,
        /** The page's content is replaced (as Mautic's ajax navigation does), then Mautic announces the page load. */
        render({links = [], lines = []}) {
            page.links = links.map(({id, href}) => new Node('a', {href: href || `/s/campaign-rebuild/${id}`, 'data-toggle': 'ajax'}));
            page.lines = lines.map(({id, text}) => new Node('div', {class: 'text-muted mt-4 campaign-updating-line', 'data-campaign-id': String(id), 'data-updating-text': 'Atualizando...'}));
            page.lines.forEach((line, i) => line.children.push(...(lines[i].text ? [lines[i].text] : [])));

            return Mautic.onPageLoad();
        },
        click(target) {
            const event = {target, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }};
            clickHandler.fn(event);

            return event;
        },
        advance(ms) {
            const end = now + ms;

            for (;;) {
                timers.sort((a, b) => a.at - b.at || a.id - b.id);
                const next = timers[0];

                if (!next || next.at > end) {
                    break;
                }

                timers.shift();
                now = next.at;
                next.fn();
            }

            now = end;
        },
        pendingTimers() { return timers.length; },
        /** Answers the oldest status request not answered yet. */
        answer(running) {
            const call = ajax.find((c) => !c.answered);
            call.answered = true;
            call.done.forEach((cb) => cb({running}));
        },
        fail() {
            const call = ajax.find((c) => !c.answered);
            call.answered = true;
            call.fail.forEach((cb) => cb());
        },
        capture() { return clickHandler.capture; },
    };

    return env;
}

const isLocked = (a) => a.classes.has('disabled') && a.attrs['aria-disabled'] === 'true' && a.style['pointer-events'] === 'none';

const OLD = '2026-10-07T12:00:00-03:00';
const NEW = '2026-10-07T12:05:00-03:00';


test('a click locks that campaign\'s button and does not ask at once: the first question comes one poll later', () => {
    const env = boot();
    env.render({links: [{id: 7, href: '/s/campaign-rebuild/7?return=view'}]});
    const [a] = env.page.links;

    const event = env.click(a);
    env.advance(0);

    assert.strictEqual(event.prevented, false, 'the first click goes on to core');
    assert.ok(isLocked(a));
    assert.strictEqual(env.ajax.length, 0, 'the click request has not had time to start the update yet');

    env.advance(POLL);
    assert.strictEqual(env.ajax.length, 1);
    assert.strictEqual(env.ajax[0].opts.url, '/s/campaign-rebuild/7/status');
    assert.strictEqual(env.ajax[0].opts.global, false);
});

test('clicking again while locked is swallowed; another campaign is still free', () => {
    const env = boot();
    env.render({links: [{id: 7}, {id: 8}]});
    const [a7, a8] = env.page.links;
    env.click(a7);
    env.advance(0);

    const again = env.click(a7);
    const other = env.click(a8);

    assert.strictEqual(again.prevented, true);
    assert.strictEqual(again.stopped, true);
    assert.strictEqual(other.prevented, false);
});

test('the clicked campaign\'s line becomes the spinner and "Atualizando..."; the others stay empty', () => {
    const env = boot();
    env.render({links: [{id: 7}], lines: [{id: 7}, {id: 8}]});
    env.click(env.page.links[0]);
    env.advance(0);
    const [line7, line8] = env.page.lines;

    const small = line7.children[0];
    assert.ok(small.classes.has('mautic-patches-updating'));
    assert.strictEqual(small.children[0].tag, 'svg');
    assert.deepStrictEqual(small.children[1].children, ['Atualizando...']);
    assert.deepStrictEqual(line8.children, []);
});

test('the page is replaced under the lock: lock and line are put back on the new one', () => {
    const env = boot();
    env.render({links: [{id: 7}], lines: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(0);

    const result = env.render({links: [{id: 7}], lines: [{id: 7}]});

    assert.strictEqual(result, 'original-result');
    assert.ok(isLocked(env.page.links[0]));
    assert.strictEqual(env.page.lines[0].children[0].tag, 'small');
});

test('while it is still running it keeps asking every 3 seconds, locked, with no reload', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);

    for (let i = 1; i <= 5; i++) {
        env.advance(POLL);
        assert.strictEqual(env.ajax.length, i);
        env.answer(true);
    }

    assert.ok(isLocked(env.page.links[0]));
    assert.deepStrictEqual(env.loads, []);
});

test('running:false means done: the page reloads (filters kept) and the button is free again', () => {
    const env = boot({path: '/s/campaigns', search: '?search=abc'});
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(POLL);
    env.answer(true);
    env.advance(POLL);

    env.answer(false);

    assert.deepStrictEqual(env.loads, ['/s/campaigns?search=abc']);
    env.render({links: [{id: 7}]});
    assert.strictEqual(isLocked(env.page.links[0]), false);
    assert.strictEqual(env.click(env.page.links[0]).prevented, false);
});

test('a quick update (done at the first question) also frees the button', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(POLL);

    env.answer(false);

    assert.deepStrictEqual(env.loads, ['/s/campaigns']);
});

test('it does not reload when the user moved to another page meanwhile, but frees the button', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(POLL);
    env.window.location.pathname = '/s/contacts';

    env.answer(false);

    assert.deepStrictEqual(env.loads, []);
    env.render({links: [{id: 7}]});
    assert.strictEqual(isLocked(env.page.links[0]), false);
});

test('after 2 minutes without an end it is freed and reloaded anyway', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);

    for (let t = 0; t < MAX; t += POLL) {
        env.advance(POLL);
        if (env.ajax.some((c) => !c.answered)) {
            env.answer(true);
        }
    }
    env.advance(POLL);

    assert.deepStrictEqual(env.loads, ['/s/campaigns']);
});

test('a failed check is retried', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(POLL);
    env.fail();
    env.advance(POLL);

    assert.strictEqual(env.ajax.length, 2);
    assert.ok(isLocked(env.page.links[0]));
});

test('the script does not touch segment links', () => {
    const env = boot();
    env.render({links: []});
    const seg = new Node('a', {href: '/s/segment-rebuild/7'});

    const event = env.click(seg);

    assert.strictEqual(event.prevented, false);
    assert.strictEqual(env.ajax.length, 0);
});

test('the click\'s answer moves the page to the list core builds: the reload still happens there', () => {
    const env = boot({path: '/s/campaigns', search: '?search=abc'});
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.window.location.pathname = '/s/campaigns/1';
    env.window.location.search = '';
    env.advance(POLL);

    env.answer(false);

    assert.deepStrictEqual(env.loads, ['/s/campaigns/1']);
});
