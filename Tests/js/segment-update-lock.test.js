// Runs the REAL script that SegmentUpdateLockSubscriber puts on the page (taken from the PHP class, not copied) against a
// small fake browser: anchors and "Updated on" lines, a fake mQuery, fake timers, and a recorder for the status requests.
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
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\SegmentUpdateLockSubscriber::script(${POLL}, ${MAX});`,
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
        return this.tag === 'a' && (this.attrs.href || '').includes('/s/segment-rebuild/');
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
function boot({path: pathname = '/s/segments', search = ''} = {}) {
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

        if (arg === '.segment-last-built') {
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
            page.links = links.map(({id, href}) => new Node('a', {href: href || `/s/segment-rebuild/${id}`, 'data-toggle': 'ajax'}));
            page.lines = lines.map(({id, text}) => new Node('div', {class: 'text-muted mt-4 segment-last-built', 'data-segment-id': String(id), 'data-updating-text': 'Atualizando...'}));
            page.lines.forEach((line, i) => line.children.push(lines[i].text || 'Atualizado em ontem'));

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
        answer(lastBuilt) {
            const call = ajax.find((c) => !c.answered);
            call.answered = true;
            call.done.forEach((cb) => cb({lastBuilt}));
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

// ---- the click --------------------------------------------------------------------------------------------------------

test('the script is found and the click handler is installed in the capture phase', () => {
    const env = boot();

    assert.ok(SCRIPT.length > 100);
    assert.strictEqual(env.capture(), true, 'before core\'s own ajax handler');
});

test('a click on Update locks that button right away and asks for the baseline date, query string left out', () => {
    const env = boot();
    env.render({links: [{id: 7, href: '/s/segment-rebuild/7?return=view'}]});
    const [a] = env.page.links;

    const event = env.click(a);
    env.advance(0);

    assert.strictEqual(event.prevented, false, 'the first click goes on to core');
    assert.strictEqual(event.stopped, false);
    assert.ok(isLocked(a));
    assert.strictEqual(env.ajax.length, 1);
    assert.strictEqual(env.ajax[0].opts.url, '/s/segment-rebuild/7/status');
    assert.strictEqual(env.ajax[0].opts.dataType, 'json');
    assert.strictEqual(env.ajax[0].opts.global, false, 'no progress bar for the polling');
});

test('a click on something that is not an Update link does nothing', () => {
    const env = boot();
    env.render({links: []});
    const other = new Node('a', {href: '/s/segments/view/7'});

    const event = env.click(other);

    assert.strictEqual(event.prevented, false);
    assert.strictEqual(env.ajax.length, 0);
});

test('a click on the icon inside the link counts as a click on the link', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    const [a] = env.page.links;
    const icon = new Node('i', {}, a);

    env.click(icon);
    env.advance(0);

    assert.ok(isLocked(a));
    assert.strictEqual(env.ajax.length, 1);
});

// ---- the lock ---------------------------------------------------------------------------------------------------------

test('clicking again while locked is swallowed and asks for nothing more', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    const [a] = env.page.links;
    env.click(a);

    const again = env.click(a);

    assert.strictEqual(again.prevented, true);
    assert.strictEqual(again.stopped, true, 'core\'s handler never sees it');
    assert.strictEqual(env.ajax.length, 1);
});

test('only the clicked segment is locked: another one can still be updated', () => {
    const env = boot();
    env.render({links: [{id: 7}, {id: 8}]});
    const [a7, a8] = env.page.links;
    env.click(a7);
    env.advance(0);

    const event = env.click(a8);
    env.advance(0);

    assert.ok(isLocked(a7));
    assert.ok(isLocked(a8));
    assert.strictEqual(event.prevented, false);
    assert.deepStrictEqual(env.ajax.map((c) => c.opts.url), ['/s/segment-rebuild/7/status', '/s/segment-rebuild/8/status']);
});

test('the other segment\'s button is not touched while one is locked', () => {
    const env = boot();
    env.render({links: [{id: 7}, {id: 8}]});
    env.click(env.page.links[0]);
    env.advance(0);

    assert.strictEqual(isLocked(env.page.links[1]), false);
});

// ---- the "Updated on" line --------------------------------------------------------------------------------------------

test('the clicked segment\'s line becomes the spinner and "Atualizando..."; the others keep their text', () => {
    const env = boot();
    env.render({links: [{id: 7}], lines: [{id: 7}, {id: 8, text: 'Atualizado em hoje'}]});
    env.click(env.page.links[0]);
    env.advance(0);
    const [line7, line8] = env.page.lines;

    const small = line7.children[0];
    assert.strictEqual(small.tag, 'small');
    assert.ok(small.classes.has('mautic-patches-updating'));
    assert.strictEqual(small.children[0].tag, 'svg', 'the spinner is an inline SVG');
    assert.ok(small.children[0].html.includes('animation:ri-spin'));
    assert.strictEqual(small.children[1].tag, 'span');
    assert.ok(small.children[1].classes.has('mautic-patches-updating-text'));
    assert.deepStrictEqual(small.children[1].children, ['Atualizando...']);
    assert.deepStrictEqual(line8.children, ['Atualizado em hoje']);
});

test('the line is rewritten once, not on every page load', () => {
    const env = boot();
    env.render({links: [{id: 7}], lines: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(0);
    const small = env.page.lines[0].children[0];

    env.Mautic.onPageLoad();

    assert.strictEqual(env.page.lines[0].children[0], small);
});

// ---- the page is replaced under the lock ------------------------------------------------------------------------------

test('the click\'s own answer replaces the page: the lock and the line are put back on the new one', () => {
    const env = boot();
    env.render({links: [{id: 7}, {id: 8}], lines: [{id: 7}, {id: 8}]});
    env.click(env.page.links[0]);
    env.advance(0);

    const result = env.render({links: [{id: 7}, {id: 8}], lines: [{id: 7}, {id: 8}]});

    assert.strictEqual(result, 'original-result', 'core\'s own onPageLoad still runs and its result is returned');
    assert.ok(isLocked(env.page.links[0]));
    assert.strictEqual(isLocked(env.page.links[1]), false);
    assert.strictEqual(env.page.lines[0].children[0].tag, 'small');
    assert.deepStrictEqual(env.page.lines[1].children, ['Atualizado em ontem']);
});

test('a click on the new page\'s locked button is still swallowed', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.render({links: [{id: 7}]});

    const again = env.click(env.page.links[0]);

    assert.strictEqual(again.prevented, true);
    assert.strictEqual(env.ajax.length, 1);
});

// ---- the polling ------------------------------------------------------------------------------------------------------

test('while the date does not change it keeps asking every 3 seconds and stays locked, with no reload', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);

    for (let i = 1; i <= 5; i++) {
        env.advance(POLL);
        assert.strictEqual(env.ajax.length, 1 + i, 'one more question every 3 s');
        env.answer(OLD);
    }

    assert.ok(isLocked(env.page.links[0]));
    assert.deepStrictEqual(env.loads, []);
});

test('it does not ask earlier than 3 seconds', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);

    env.advance(POLL - 1);

    assert.strictEqual(env.ajax.length, 1);
});

test('a different date means the rebuild is done: the page reloads and the button is free again', () => {
    const env = boot({path: '/s/segments', search: '?search=abc'});
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);
    env.advance(POLL);

    env.answer(NEW);

    assert.deepStrictEqual(env.loads, ['/s/segments?search=abc'], 'same page, filters kept');

    const fresh = env.render({links: [{id: 7}]});
    assert.ok(fresh);
    assert.strictEqual(isLocked(env.page.links[0]), false, 'the new page\'s button is free');

    const next = env.click(env.page.links[0]);
    assert.strictEqual(next.prevented, false, 'it can be updated again');
});

test('it stops asking once the date has changed', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);
    env.advance(POLL);
    env.answer(NEW);

    env.advance(POLL * 5);

    assert.strictEqual(env.ajax.length, 2);
});

test('a segment never built (null) is the baseline too, and a first date ends the wait', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(null);
    env.advance(POLL);
    env.answer(null);

    assert.deepStrictEqual(env.loads, []);

    env.advance(POLL);
    env.answer(NEW);

    assert.strictEqual(env.loads.length, 1);
});

test('the first answer is only the baseline, even if it is already a date', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.advance(0);

    env.answer(OLD);

    assert.deepStrictEqual(env.loads, []);
    assert.ok(isLocked(env.page.links[0]));
});

test('if the user left the page, the button is freed but nothing is reloaded under them', () => {
    const env = boot({path: '/s/segments'});
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);
    env.window.location.pathname = '/s/contacts';
    env.advance(POLL);

    env.answer(NEW);

    assert.deepStrictEqual(env.loads, []);
});

test('on the segment\'s own page it works the same and reloads that page', () => {
    const env = boot({path: '/s/segments/view/7'});
    env.render({links: [{id: 7, href: '/s/segment-rebuild/7?return=view'}]});
    env.click(env.page.links[0]);
    env.answer(OLD);
    env.advance(POLL);

    env.answer(NEW);

    assert.deepStrictEqual(env.loads, ['/s/segments/view/7']);
    assert.strictEqual(env.ajax[0].opts.url, '/s/segment-rebuild/7/status');
});

// ---- failures and the limit -------------------------------------------------------------------------------------------

test('a status request that fails is asked again 3 seconds later, it is not a dead end', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);
    env.advance(POLL);
    env.fail();

    env.advance(POLL);

    assert.strictEqual(env.ajax.length, 3);
    env.answer(NEW);
    assert.strictEqual(env.loads.length, 1);
});

test('if even the baseline request fails, it is taken on the next try', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.fail();
    env.advance(POLL);

    env.answer(OLD);
    env.advance(POLL);
    env.answer(NEW);

    assert.strictEqual(env.loads.length, 1);
});

test('without an end in 2 minutes it frees the button and reloads anyway', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);

    let asked = 1;
    for (let step = 0; step < 100 && env.loads.length === 0; step++) {
        env.advance(POLL);
        if (env.ajax.length > asked) {
            asked = env.ajax.length;
            env.answer(OLD);
        }
    }

    assert.deepStrictEqual(env.loads, ['/s/segments']);
    assert.ok(asked <= MAX / POLL + 1, 'it asked about 40 times at most');
    assert.ok(asked >= MAX / POLL - 1);
});

test('before 2 minutes it is still waiting', () => {
    const env = boot();
    env.render({links: [{id: 7}]});
    env.click(env.page.links[0]);
    env.answer(OLD);

    for (let t = 0; t < MAX - POLL * 2; t += POLL) {
        env.advance(POLL);
        env.answer(OLD);
    }

    assert.deepStrictEqual(env.loads, []);
    assert.ok(isLocked(env.page.links[0]));
});

// ---- several at once --------------------------------------------------------------------------------------------------

test('two segments are followed independently: one finishing does not free the other', () => {
    const env = boot();
    env.render({links: [{id: 7}, {id: 8}]});
    env.click(env.page.links[0]);
    env.click(env.page.links[1]);
    env.answer(OLD);
    env.answer('2026-09-01T00:00:00-03:00');
    env.advance(POLL);

    // seven's second answer is a new date; eight's is the same
    const [call7, call8] = env.ajax.filter((c) => !c.answered);
    call7.answered = true;
    call7.done.forEach((cb) => cb({lastBuilt: NEW}));
    call8.answered = true;
    call8.done.forEach((cb) => cb({lastBuilt: '2026-09-01T00:00:00-03:00'}));
    env.render({links: [{id: 7}, {id: 8}]});

    assert.strictEqual(isLocked(env.page.links[0]), false, 'seven is done');
    assert.ok(isLocked(env.page.links[1]), 'eight is still running');
});

// ---- installing -------------------------------------------------------------------------------------------------------

test('running the script twice installs it once (a page that injects it again must not double the polling)', () => {
    const env = boot();
    const handlersBefore = env.Mautic.onPageLoad;

    // the same window, the script again
    let clicks = 0;
    const sandbox = {window: env.window, document: {addEventListener() { clicks++; }}, mQuery: () => {}, Mautic: env.Mautic, Date, setTimeout};
    vm.runInNewContext(SCRIPT, sandbox);

    assert.strictEqual(clicks, 0);
    assert.strictEqual(env.Mautic.onPageLoad, handlersBefore);
});

test('nothing is written to the browser\'s storage', () => {
    for (const storage of ['localStorage', 'sessionStorage', 'cookie', 'indexedDB']) {
        assert.ok(!SCRIPT.includes(storage), storage);
    }
});
