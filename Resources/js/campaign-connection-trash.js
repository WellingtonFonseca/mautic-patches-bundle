/*
 * A button on every line (a broken link icon) of the campaign builder: moving the mouse over a line (between any two nodes, the contact source
 * included) shows it in the middle of the line, and a click disconnects the line. Core only disconnects by grabbing the half
 * circle (the endpoint) of the node and dragging the line away.
 *
 * The click calls jsPlumb's deleteConnection, the same call core makes when a line is replaced: it runs core's own
 * beforeDetach check and fires connectionDetached, so the colors of the points and the labels follow as they do after a drag.
 * The button sits above the lines (core gives their svg z-index 1000). It is a jsPlumb "Custom" overlay (core only removes the overlays of type Label, see
 * Mautic.campaignBuilderUpdateLabel), so it follows the line when a node moves.
 *
 * The circle above a node (where its line comes in) shows a second button, just above the circle, when the mouse is over the circle:
 * it disconnects the line that comes into that node, so the line does not have to be aimed at where other lines cross.
 *
 * The line is 2 px thick and hard to hit: a transparent copy of its path, 16 px thick, is the area that shows the button, and
 * it is kept in step with the line by watching the line's `d` attribute. The button stays while the mouse is over the area
 * or over the button itself, and goes away a moment after the mouse leaves both.
 */
(function (root) {
    'use strict';

    var OVERLAY_ID = 'mauticPatchesTrash';
    var HIT_WIDTH  = 16;
    var HIDE_AFTER = 250;
    var ABOVE      = 2;

    var shown = null;
    var circleShown = false;

    function makeButton(title) {
        var button = root.document.createElement('a');
        button.href = 'javascript:void(0);';
        button.className = 'btn btn-danger btn-xs btn-nospin mautic-patches-trash';
        button.title = title;
        button.innerHTML = '<i class="ri-link-unlink ri-lg"></i>';
        button.style.cssText = 'display:none;position:absolute;z-index:1100;color:#fff;padding:4px 8px;line-height:1;cursor:pointer;transition:none;margin:0';

        return button;
    }

    // one button at a time: the one of a line the mouse just crossed goes as the next one shows. `before` runs when the button
    // is about to show and can refuse (false) or place it; the button stays while the mouse is over it, and goes a moment after.
    // The button of a circle (`circle`) sits on its own line, so while it is shown the lines do not take its place.
    function bindHover(button, before, circle) {
        var timer = null;
        function hideNow() { clearTimeout(timer); button.style.display = 'none'; if (circle) { circleShown = false; } }
        function show() {
            if (!circle && circleShown) { return; }
            clearTimeout(timer);
            if (button.style.display === 'none' && before) {
                button.style.display = 'block';
                if (before() === false) { button.style.display = 'none'; return; }
            }
            if (shown && shown !== hideNow) { shown(); }
            shown = hideNow;
            if (circle) { circleShown = true; }
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
        if (connection.mauticPatchesTrash || !connection.canvas) { return; }
        connection.mauticPatchesTrash = true;

        var button = makeButton(title);

        connection.addOverlay(['Custom', {id: OVERLAY_ID, location: 0.5, create: function () { return button; }}]);

        var hover = bindHover(button);
        var show = hover.show, hide = hover.hide;

        // the path of the line is drawn after the connection is made: wait for it
        function attachHit(line) {
            var hit = line.cloneNode(false);
            hit.setAttribute('class', 'mautic-patches-trash-hit');
            hit.setAttribute('stroke', 'transparent');
            hit.setAttribute('stroke-width', String(HIT_WIDTH));
            hit.setAttribute('fill', 'none');
            hit.removeAttribute('marker-end');
            hit.style.pointerEvents = 'stroke';
            line.parentNode.insertBefore(hit, line);
            new root.MutationObserver(function () { hit.setAttribute('d', line.getAttribute('d')); })
                .observe(line, {attributes: true, attributeFilter: ['d']});
            // the line itself sits on top of its hit area: the mouse right on it must count too
            [hit, line].forEach(function (part) {
                part.addEventListener('mouseenter', show);
                part.addEventListener('mouseleave', hide);
            });
        }
        var line = connection.canvas.querySelector('path');
        if (line) {
            attachHit(line);
        } else {
            var waiting = new root.MutationObserver(function () {
                var drawn = connection.canvas.querySelector('path');
                if (drawn) { waiting.disconnect(); attachHit(drawn); }
            });
            waiting.observe(connection.canvas, {childList: true, subtree: true});
        }
        bindClick(button, function () { instance.deleteConnection(connection); });

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
        }, true);
        circle.addEventListener('mouseenter', hover.show);
        circle.addEventListener('mouseleave', hover.hide);
        bindClick(button, function () { instance.deleteConnection(connection); });
        // a later line into the same circle gets its own button: this one goes with its line
        connection.mauticPatchesCircleTrash = button;
    }

    function instrument(instance, title) {
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
        style.textContent = '.mautic-patches-trash-hit{cursor:pointer}.mautic-patches-trash i{pointer-events:none;color:#fff}';
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
