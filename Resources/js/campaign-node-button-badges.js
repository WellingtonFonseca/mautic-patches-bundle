/*
 * The node buttons of the campaign builder (Clonar, Editar, Remover: `.campaign-event-buttons`, top right of a node) become icon badges
 * like the disconnect badge of campaign-connection-trash.js: a 29px circle with the icon that opens to show its label on hover. The
 * buttons are core's own links and stay in place (same order, same colors, same handlers: ajax / ajaxmodal); only a class and a label
 * span are added, so nothing of core is reimplemented. The buttons stand in a column at the right side of the node (half of each badge inside the node, centred on its right edge); the icon stays and only the right side of the badge opens to the right.
 * Nodes made later (a clone, a new node) are decorated as they come into the page.
 */
(function (root) {
    'use strict';

    var KINDS = {'btn-clone': 'clone', 'btn-edit': 'edit', 'btn-delete': 'remove'};

    var STYLE = '.campaign-event-buttons{display:flex;flex-direction:column;align-items:flex-start;gap:3px;width:max-content;top:50%;right:auto;left:calc(100% - 14.5px);transform:translateY(-50%);white-space:nowrap}'
        + '.campaign-event-buttons.hide{display:none}'
        // the icon of a node with buttons is 30% bigger (60px -> 78px, 20px -> 26px) so the node is tall enough for the column of three badges
        + '.list-campaign-event:has(.campaign-event-buttons) .campaign-event-icon{height:78px;font-size:26px}'
        // the strip of the badges: core's 260px minimum width is 20px wider and the text keeps off the strip, so a long text widens the node instead of going under the badges
        + '.list-campaign-event:has(.campaign-event-buttons){min-width:280px}'
        + '.list-campaign-event:has(.campaign-event-buttons) .campaign-event-content{padding-right:24px}'
        // the status badge of the n8n action card (top-left, n8n-dispatch plugin) gets the size of these badges: 29px circle, same opening and label
        + '.list-campaign-event .n8ndispatch-status-badge{height:29px;max-width:29px;border-radius:14.5px}'
        + '.list-campaign-event .n8ndispatch-status-badge:hover{max-width:160px}'
        + '.list-campaign-event .n8ndispatch-status-badge i{flex:0 0 29px;width:29px;height:29px;font-size:17px}'
        + '.list-campaign-event .n8ndispatch-status-badge__label{font-size:14px;padding:0 12px 0 0}'
        // the card "Insert cloned event here" (shown once a node was cloned) stands at the right of the cards of Decision / Action / Condition, one more column of the same size, instead of a full-width row below them where a long canvas hides it
        + '#CampaignEventPanelGroups:has(#CampaignPasteContainer:not(.hide)){display:flex;align-items:stretch}'
        + '#CampaignEventPanelGroups:has(#CampaignPasteContainer:not(.hide))>.row{margin-left:0;margin-right:0}'
        + '#CampaignEventPanelGroups:has(#CampaignPasteContainer:not(.hide))>.row:first-child{flex:1 1 0}'
        + '#CampaignEventPanelGroups.groups-enabled-1:has(#CampaignPasteContainer:not(.hide))>.row:first-child{flex-grow:1}'
        + '#CampaignEventPanelGroups.groups-enabled-2:has(#CampaignPasteContainer:not(.hide))>.row:first-child{flex-grow:2}'
        + '#CampaignEventPanelGroups.groups-enabled-3:has(#CampaignPasteContainer:not(.hide))>.row:first-child{flex-grow:3}'
        + '#CampaignEventPanelGroups:has(#CampaignPasteContainer:not(.hide))>.row:last-child{flex:1 1 0}'
        + '#CampaignEventPanelGroups:has(#CampaignPasteContainer:not(.hide))>.row{min-width:0}'
        + '#CampaignEventPanelGroups #CampaignPasteContainer{height:100%;display:flex;flex-direction:column}'
        + '#CampaignEventPanelGroups #CampaignPasteContainer .panel-body{flex:1 1 auto}'
        + '#CampaignEventPanelGroups #CampaignPasteContainer .panel-body>div{max-width:none;white-space:normal;overflow:visible;text-overflow:clip}'
        // and the panel is one column wider (core sets 167px per group inline), centred on the same point as before
        + '#CampaignEventPanel:has(#CampaignEventPanelGroups.groups-enabled-1:not(.hide) #CampaignPasteContainer:not(.hide)){width:333px!important;margin-left:-83px}'
        + '#CampaignEventPanel:has(#CampaignEventPanelGroups.groups-enabled-2:not(.hide) #CampaignPasteContainer:not(.hide)){width:500px!important;margin-left:-83px}'
        + '#CampaignEventPanel:has(#CampaignEventPanelGroups.groups-enabled-3:not(.hide) #CampaignPasteContainer:not(.hide)){width:667px!important;margin-left:-83px}'
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
