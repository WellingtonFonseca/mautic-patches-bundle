/*
 * The node buttons of the campaign builder (Clonar, Editar, Remover: `.campaign-event-buttons`, top right of a node) become icon badges
 * like the disconnect badge of campaign-connection-trash.js: a 29px circle with the icon that opens to show its label on hover. The
 * buttons are core's own links and stay in place (same order, same colors, same handlers: ajax / ajaxmodal); only a class and a label
 * span are added, so nothing of core is reimplemented. The buttons stand in a column at the right side of the node (the padding keeps the node :hover while the mouse crosses to them); the icon stays and only the right side of the badge opens to the right.
 * Nodes made later (a clone, a new node) are decorated as they come into the page.
 */
(function (root) {
    'use strict';

    var KINDS = {'btn-clone': 'clone', 'btn-edit': 'edit', 'btn-delete': 'remove'};

    var STYLE = '.campaign-event-buttons{display:flex;flex-direction:column;align-items:flex-start;gap:4px;width:max-content;top:0;right:auto;left:100%;padding-left:6px;white-space:nowrap}'
        + '.campaign-event-buttons.hide{display:none}'
        + '.campaign-event-buttons .patches-node-badge{display:inline-flex;align-items:center;justify-content:flex-start;box-sizing:border-box;flex:0 0 auto;width:29px;height:29px;min-width:0;margin:0;padding:0;gap:0;overflow:hidden;border-radius:14.5px;'
        + 'font-size:14px;font-weight:600;line-height:normal;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap;text-decoration:none;transition:width .2s ease,box-shadow .2s ease}'
        + '.campaign-event-buttons .patches-node-badge:hover{box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;transform:none;text-decoration:none}'
        + '.campaign-event-buttons .patches-node-badge>i{order:0;flex:0 0 29px;width:29px;height:29px;font-size:17px;display:flex;align-items:center;justify-content:center;pointer-events:none}'
        + '.campaign-event-buttons .patches-node-badge__text{padding:0 12px 0 0;opacity:0;pointer-events:none;transition:opacity .15s ease .05s}'
        + '.campaign-event-buttons .patches-node-badge:hover .patches-node-badge__text{opacity:1}';

    function openBadge() { this.style.width = (29 + this.querySelector('.patches-node-badge__text').scrollWidth) + 'px'; }
    function closeBadge() { this.style.width = ''; }

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
            // core's `.btn span` rules make the intrinsic width of the link just the icon, so the opening is an explicit width, measured when the mouse comes in
            link.addEventListener('mouseenter', openBadge);
            link.addEventListener('mouseleave', closeBadge);
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
