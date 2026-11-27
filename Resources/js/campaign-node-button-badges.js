/*
 * The node buttons of the campaign builder (Clonar, Editar, Remover: `.campaign-event-buttons`, top right of a node) become icon badges
 * like the disconnect badge of campaign-connection-trash.js: a 29px circle with the icon that opens to show its label on hover. The
 * buttons are core's own links and stay in place (same order, same colors, same handlers: ajax / ajaxmodal); only a class and a label
 * span are added, so nothing of core is reimplemented. The label opens inside the flex row of the buttons and pushes its neighbours.
 * Nodes made later (a clone, a new node) are decorated as they come into the page.
 */
(function (root) {
    'use strict';

    var KINDS = {'btn-clone': 'clone', 'btn-edit': 'edit', 'btn-delete': 'remove'};

    var STYLE = '.campaign-event-buttons{display:flex;align-items:center;gap:4px;white-space:nowrap}'
        + '.campaign-event-buttons.hide{display:none}'
        + '.campaign-event-buttons .patches-node-badge{display:inline-flex;align-items:center;justify-content:flex-start;box-sizing:border-box;height:29px;min-width:0;max-width:29px;margin:0;padding:0;gap:0;overflow:hidden;border-radius:14.5px;'
        + 'font-size:14px;font-weight:600;line-height:normal;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap;text-decoration:none;transition:max-width .2s ease,box-shadow .2s ease}'
        + '.campaign-event-buttons .patches-node-badge:hover{max-width:160px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;transform:none;text-decoration:none}'
        + '.campaign-event-buttons .patches-node-badge>i{flex:0 0 29px;width:29px;height:29px;font-size:17px;display:flex;align-items:center;justify-content:center;pointer-events:none}'
        + '.campaign-event-buttons .patches-node-badge__text{padding:0 12px 0 0;opacity:0;pointer-events:none;transition:opacity .15s ease .05s}'
        + '.campaign-event-buttons .patches-node-badge:hover .patches-node-badge__text{opacity:1}';

    // Decorates every node button inside `scope` that is not decorated yet. Returns how many it decorated.
    function decorate(scope, labels, doc) {
        var links = scope.querySelectorAll('.campaign-event-buttons > a.btn');
        var done = 0;
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            if (link.classList.contains('patches-node-badge')) { continue; }
            var kind = null;
            for (var cls in KINDS) { if (link.classList.contains(cls)) { kind = KINDS[cls]; } }
            if (!kind) { continue; }
            var span = doc.createElement('span');
            span.className = 'patches-node-badge__text';
            span.textContent = labels[kind] || (link.getAttribute('aria-label') || '').trim();
            link.classList.add('patches-node-badge');
            link.appendChild(span);
            done++;
        }

        return done;
    }

    function hasButtons(nodes) {
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeType === 1 && (node.matches('.campaign-event-buttons') || node.querySelector('.campaign-event-buttons'))) { return true; }
        }

        return false;
    }

    function install(win, labels) {
        var doc = win.document;
        var style = doc.createElement('style');
        style.textContent = STYLE;
        doc.head.appendChild(style);

        var timer = null;
        function run() { decorate(doc, labels, doc); }
        run();
        if (win.MutationObserver) {
            new win.MutationObserver(function (mutations) {
                if (mutations.some(function (m) { return hasButtons(m.addedNodes); })) { clearTimeout(timer); timer = setTimeout(run, 0); }
            }).observe(doc.body, {childList: true, subtree: true});
        }
    }

    root.mauticPatchesNodeButtonBadgesApi = {decorate: decorate, hasButtons: hasButtons, install: install};

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.mauticPatchesNodeButtonBadgesApi; }
})(typeof window !== 'undefined' ? window : this);
