/*
 * The node buttons of the campaign builder (Clonar, Editar, Remover) have no tooltip. Core gives each icon a Bootstrap tooltip
 * (data-toggle="tooltip", drawn in the body), and Bootstrap only hides it on the mouseleave of the button: when the button goes away
 * under the mouse (the node was removed, or the buttons were hidden when the edit modal opened) that event never comes and the tooltip
 * stays on the canvas, over the buttons of the nodes around, until the page is reloaded.
 *
 * Each icon of `.campaign-event-buttons` is stripped of its tooltip: destroyed through Bootstrap and the attributes that make it
 * (data-toggle, title, data-original-title, data-placement) removed, so core's own tooltip activation (it runs again on every page
 * load over ajax and on what an ajax call inserts, a clone for one) finds nothing to activate. The buttons keep core's aria-label.
 * Nodes made later (a clone, a new node) are stripped as they come into the page. The tooltips of anything else (the counts of the
 * Preview tab) are not touched.
 */
(function (root) {
    'use strict';

    var SELECTOR = '.campaign-event-buttons [data-toggle="tooltip"]';
    var ATTRIBUTES = ['data-toggle', 'title', 'data-original-title', 'data-placement'];

    // Strips the tooltip of every node button inside `scope` (a document or an element). Returns how many it stripped.
    function strip(scope, jq, doc) {
        var icons = scope.querySelectorAll(SELECTOR);
        for (var i = 0; i < icons.length; i++) {
            var icon = icons[i];
            var tipId = icon.getAttribute('aria-describedby');
            if (jq) { try { jq(icon).tooltip('destroy'); } catch (e) { /* the plugin may not be loaded yet */ } }
            if (tipId) {
                var tip = doc.getElementById(tipId);
                if (tip && tip.parentNode) { tip.parentNode.removeChild(tip); }
                icon.removeAttribute('aria-describedby');
            }
            ATTRIBUTES.forEach(function (name) { icon.removeAttribute(name); });
        }

        return icons.length;
    }

    // true when one of the nodes just added to the page is, or holds, a `.campaign-event-buttons`
    function hasButtons(nodes) {
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeType === 1 && (node.matches('.campaign-event-buttons') || node.querySelector('.campaign-event-buttons'))) { return true; }
        }

        return false;
    }

    function install(win) {
        var doc = win.document;
        var jq  = win.mQuery || win.jQuery;
        var timer = null;

        function run() { strip(doc, jq, doc); }
        function soon() { clearTimeout(timer); timer = setTimeout(run, 0); }

        run();
        if (win.MutationObserver) {
            // only what brings node buttons into the page counts: every other change of the page (lists, modals) costs nothing
            new win.MutationObserver(function (mutations) {
                if (mutations.some(function (m) { return hasButtons(m.addedNodes); })) { soon(); }
            }).observe(doc.body, {childList: true, subtree: true});
        }
    }

    root.mauticPatchesNodeButtonTooltipsApi = {strip: strip, hasButtons: hasButtons, install: install};

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.mauticPatchesNodeButtonTooltipsApi; }
})(typeof window !== 'undefined' ? window : this);
