/*
 * A button that disconnects the line that comes into a node (a broken link icon): moving the mouse over the circle above the node
 * (the point where the line ends, the contact source's lines included) shows it just above the circle, and a click disconnects
 * that line. The circle belongs to one line only, so there is no doubt about which line goes, which a button on the line would
 * not tell where lines cross. Core only disconnects by grabbing the half circle (the endpoint) of the node and dragging the line
 * away.
 *
 * The click calls jsPlumb's deleteConnection, the same call core makes when a line is replaced: it runs core's own
 * beforeDetach check and fires connectionDetached, so the colors of the points and the labels follow as they do after a drag.
 * The button sits above the circle (core gives the svg of the lines z-index 1000, CampaignEndpointsOnTopSubscriber puts the
 * circles at 1011). It stays while the mouse is over the circle or over the button itself, and goes away a moment after the
 * mouse leaves both. The circle outlives its line: a line that is gone shows nothing, and a line made later into the same
 * circle gets its own button.
 */
(function (root) {
    'use strict';

    var HIDE_AFTER = 250;
    var ABOVE      = 2;

    var shown = null;

    function makeButton(title) {
        var button = root.document.createElement('a');
        button.href = 'javascript:void(0);';
        button.className = 'btn btn-danger btn-xs btn-nospin mautic-patches-trash';
        button.title = title;
        button.innerHTML = '<i class="ri-link-unlink ri-lg"></i>';
        button.style.cssText = 'display:none;position:absolute;z-index:1100;color:#fff;padding:4px 8px;line-height:1;cursor:pointer;transition:none;margin:0';

        return button;
    }

    // one button at a time. `before` runs when the button is about to show and can refuse (false) or place it; the button stays
    // while the mouse is over it, and goes a moment after.
    function bindHover(button, before) {
        var timer = null;
        function hideNow() { clearTimeout(timer); button.style.display = 'none'; }
        function show() {
            clearTimeout(timer);
            if (button.style.display === 'none' && before) {
                button.style.display = 'block';
                if (before() === false) { button.style.display = 'none'; return; }
            }
            if (shown && shown !== hideNow) { shown(); }
            shown = hideNow;
            button.style.display = '';
        }
        function hide() { clearTimeout(timer); timer = setTimeout(hideNow, HIDE_AFTER); }
        button.addEventListener('mouseenter', show);
        button.addEventListener('mouseleave', hide);

        return {show: show, hide: hide};
    }

    function bindClick(button, action) {
        button.addEventListener('mousedown', function (e) { e.stopPropagation(); });
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            action();
        });
    }

    // the line is decorated once; a line made later (a new connection) is decorated by the 'connection' event
    function decorate(instance, connection, title) {
        if (connection.mauticPatchesTrash) { return; }
        connection.mauticPatchesTrash = true;
        decorateEndpoint(instance, connection, title);
    }

    // the button of the circle where the line comes in: above the circle, shown while the mouse is over the circle or the button
    function decorateEndpoint(instance, connection, title) {
        var endpoint = connection.endpoints && connection.endpoints[1];
        var circle   = endpoint && endpoint.canvas;
        if (!circle || !circle.parentNode) { return; }

        var button = makeButton(title);
        button.className += ' mautic-patches-trash-endpoint';
        circle.parentNode.appendChild(button);

        var hover = bindHover(button, function () {
            // the circle outlives the line: a line that is gone (or one replaced by a new line) shows nothing
            if (instance.getAllConnections().indexOf(connection) === -1) { return false; }
            button.style.left = (circle.offsetLeft + circle.offsetWidth / 2 - button.offsetWidth / 2) + 'px';
            button.style.top  = (circle.offsetTop - button.offsetHeight - ABOVE) + 'px';

            return true;
        });
        circle.addEventListener('mouseenter', hover.show);
        circle.addEventListener('mouseleave', hover.hide);
        bindClick(button, function () { instance.deleteConnection(connection); });
        // a later line into the same circle gets its own button: this one goes with its line
        connection.mauticPatchesCircleTrash = button;
    }

    // The Preview tab of the campaign page draws its canvas with the same function (core calls prepareCampaignCanvas for it too), but it only
    // shows the campaign: nothing there is disconnected, so its canvas is left as core makes it (its container is inside #preview-container).
    function isPreview(instance) {
        var container = instance.getContainer ? instance.getContainer() : null;

        return !!(container && container.closest && container.closest('#preview-container'));
    }

    function instrument(instance, title) {
        if (isPreview(instance)) { return; }
        if (!instance.mauticPatchesTrashBound) {
            instance.mauticPatchesTrashBound = true;
            instance.bind('connection', function (info) { decorate(instance, info.connection, title); });
            instance.bind('connectionDetached', function (info) {
                var button = info.connection.mauticPatchesCircleTrash;
                if (button) { button.remove(); }
            });
        }
        instance.getAllConnections().forEach(function (connection) { decorate(instance, connection, title); });
    }

    function install(Mautic, cfg) {
        if (root.mauticPatchesConnectionTrash) { return; }
        root.mauticPatchesConnectionTrash = true;

        var style = root.document.createElement('style');
        style.textContent = '.mautic-patches-trash i{pointer-events:none;color:#fff}';
        root.document.head.appendChild(style);

        // campaign.js may load after this script, and the canvas is made when the builder opens: the function that makes it
        // is wrapped (once) on every page load, and a canvas that already exists is decorated at once
        function run() {
            var prepare = Mautic.prepareCampaignCanvas;
            if (prepare && !prepare.mauticPatchesTrash) {
                var wrapped = function () {
                    var result = prepare.apply(this, arguments);
                    if (Mautic.campaignBuilderInstance) { instrument(Mautic.campaignBuilderInstance, cfg.title); }

                    return result;
                };
                wrapped.mauticPatchesTrash = true;
                Mautic.prepareCampaignCanvas = wrapped;
            }
            if (Mautic.campaignBuilderInstance) { instrument(Mautic.campaignBuilderInstance, cfg.title); }
        }

        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        run();
    }

    root.mauticPatchesConnectionTrashApi = {install: install};
})(typeof window !== 'undefined' ? window : this);
