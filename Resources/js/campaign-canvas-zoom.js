/*
 * Zoom of the campaign builder canvas: four buttons below Save and Close (zoom out, the percentage that resets it, zoom in,
 * fit every node on the screen), CTRL + wheel over the canvas and CTRL + "+", "-", "0". Both on the builder and on the Preview tab of the campaign page.
 *
 * The canvas gets `transform: scale(z)` with the origin at its top left, and jsPlumb gets the same factor (setZoom): without it
 * the lines, the drags and the new connections are measured at 100% and miss the nodes. The scroll bars belong to
 * `.builder-content`, which is not scaled, so the pan (CampaignCanvasPanSubscriber) needs no change.
 * The point under the mouse (or the centre of the view, for the buttons) stays where it is: the scroll moves by the growth.
 * The maths are plain functions, tested in Node (Tests/js/campaign-canvas-zoom.test.js).
 */
(function (root) {
    'use strict';

    var MIN = 0.3;
    var MAX = 2;
    var STEP = 0.1;
    var WHEEL_FULL = 100; // a deltaY of this size (one notch of a mouse wheel) is one step; a pinch sends smaller ones

    function clamp(zoom) {
        return Math.min(MAX, Math.max(MIN, Math.round(zoom * 100) / 100));
    }

    // CTRL + wheel: up (negative deltaY) zooms in
    function wheelZoom(zoom, deltaY) {
        var size = Math.min(Math.abs(deltaY), WHEEL_FULL) / WHEEL_FULL;

        return clamp(zoom + (deltaY < 0 ? 1 : -1) * size * STEP);
    }

    // scroll position that keeps the point (px, py), measured inside the view, on the same spot of the canvas
    function anchoredScroll(scroll, point, from, to) {
        return (scroll + point) * (to / from) - point;
    }

    // Zoom and scroll that show every rectangle {x, y, width, height} (canvas pixels at 100%) inside the view {width, height},
    // centred, with `pad` around them. It never zooms in past 100%: a small campaign is not blown up.
    function fitView(rects, view, pad) {
        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        rects.forEach(function (r) {
            minX = Math.min(minX, r.x);
            minY = Math.min(minY, r.y);
            maxX = Math.max(maxX, r.x + r.width);
            maxY = Math.max(maxY, r.y + r.height);
        });
        var width = maxX - minX + 2 * pad;
        var height = maxY - minY + 2 * pad;
        var z = clamp(Math.min(1, view.width / width, view.height / height));

        return {
            zoom: z,
            left: Math.max(0, (minX - pad) * z - (view.width - width * z) / 2),
            top: Math.max(0, (minY - pad) * z - (view.height - height * z) / 2),
        };
    }

    // CTRL + "+", "-" and "0": the key tells what to do (null: not a zoom key)
    function keyAction(key) {
        if (key === '+' || key === '=') { return 'in'; }
        if (key === '-' || key === '_') { return 'out'; }
        if (key === '0') { return 'reset'; }

        return null;
    }

    var api = {clamp: clamp, wheelZoom: wheelZoom, anchoredScroll: anchoredScroll, fitView: fitView, keyAction: keyAction, MIN: MIN, MAX: MAX, STEP: STEP, FIT_PAD: 60};

    function install(Mautic, cfg) {
        var doc = root.document;
        if (root.mauticPatchesCanvasZoom) { return; }
        root.mauticPatchesCanvasZoom = true;

        var zoom = 1;
        var labels = [];
        var overPreview = false;

        function shown(el) { return el && el.getClientRects().length > 0 ? el : null; }
        // the box that scrolls: `.builder-content` in the builder, `#preview-container` on the Preview tab of the campaign page
        function builderView() { return shown(doc.querySelector('#campaign-builder:not(.preview) .builder-content')); }
        function previewView() { return shown(doc.getElementById('preview-container')); }
        function viewAt(target) {
            return [builderView(), previewView()].filter(function (v) { return v && v.contains(target); })[0] || null;
        }
        // the keys act on the builder when it is open; on the Preview only while the mouse is over it (CTRL + "+" is also the browser's zoom)
        function keyView() { return builderView() || (overPreview ? previewView() : null); }

        function apply() {
            var canvas = doc.getElementById('CampaignCanvas');
            if (!canvas) { return; }
            canvas.style.transformOrigin = '0 0';
            canvas.style.transform = 'scale(' + zoom + ')';
            if (Mautic.campaignBuilderInstance) {
                Mautic.campaignBuilderInstance.setZoom(zoom);
                Mautic.campaignBuilderInstance.repaintEverything();
            }
            // an ajax visit to another campaign page replaces the group: drop the buttons that left the page, or the array only grows
            labels = labels.filter(function (label) { return label.isConnected !== false; });
            labels.forEach(function (label) { label.textContent = Math.round(zoom * 100) + '%'; });
        }

        // `point` is a position inside the view (default: its centre)
        function setZoom(next, point, view) {
            next = clamp(next);
            if (!view || next === zoom) { return; }
            var p = point || {x: view.clientWidth / 2, y: view.clientHeight / 2};
            var from = zoom;
            zoom = next;
            apply();
            view.scrollLeft = anchoredScroll(view.scrollLeft, p.x, from, zoom);
            view.scrollTop = anchoredScroll(view.scrollTop, p.y, from, zoom);
        }

        // every node of the canvas, as a rectangle at 100% (offsets ignore the scale)
        function nodeRects() {
            var nodes = doc.querySelectorAll('#CampaignCanvas .list-campaign-event, #CampaignCanvas .list-campaign-source');
            var rects = [];
            Array.prototype.forEach.call(nodes, function (n) {
                if (n.offsetWidth > 0) { rects.push({x: n.offsetLeft, y: n.offsetTop, width: n.offsetWidth, height: n.offsetHeight}); }
            });

            return rects;
        }

        function fit(view) {
            var rects = nodeRects();
            if (!view || !rects.length) { return; }
            var result = fitView(rects, {width: view.clientWidth, height: view.clientHeight}, api.FIT_PAD);
            zoom = result.zoom;
            apply();
            view.scrollLeft = result.left;
            view.scrollTop = result.top;
        }

        // `ghost`: the buttons of the builder's header (Mautic 7 draws them flat); the Preview keeps the secondary ones
        function makeButton(icon, title, onclick, ghost) {
            var button = doc.createElement('button');
            button.type = 'button';
            button.className = 'btn ' + (ghost ? 'btn-ghost' : 'btn-secondary') + ' btn-nospin mautic-patches-zoom-button';
            button.title = title;
            button.setAttribute('aria-label', title);
            button.innerHTML = icon ? '<i class="' + icon + '"></i>' : '';
            button.addEventListener('click', onclick);

            return button;
        }

        // the four buttons; `getView` tells which box they zoom (the builder's or the Preview's)
        function makeGroup(getView, ghost) {
            var group = doc.createElement('span');
            group.className = 'mautic-patches-zoom' + (ghost ? ' mautic-patches-zoom--header' : '');
            var reset = makeButton('', cfg.zoomReset, function () { setZoom(1, null, getView()); }, ghost);
            reset.textContent = Math.round(zoom * 100) + '%';
            labels.push(reset);
            group.appendChild(makeButton('ri-zoom-out-line', cfg.zoomOut, function () { setZoom(zoom - STEP, null, getView()); }, ghost));
            group.appendChild(reset);
            group.appendChild(makeButton('ri-zoom-in-line', cfg.zoomIn, function () { setZoom(zoom + STEP, null, getView()); }, ghost));
            group.appendChild(makeButton('ri-fullscreen-line', cfg.zoomFit, function () { fit(getView()); }, ghost));

            return group;
        }

        // the builder's header is in the page from the start (hidden with the builder); the Preview is rendered with the campaign page
        // and replaced by ajax (campaign.js) each time its date range changes
        function addButtons() {
            var bar = doc.querySelector('#campaign-builder:not(.preview) .header__global');
            if (bar && !bar.querySelector('.mautic-patches-zoom')) {
                // before the auto organize button, in the row of Save and Close
                bar.insertBefore(makeGroup(builderView, true), bar.firstChild);
            }
            var container = doc.getElementById('preview-container');
            if (container && !container.querySelector('.mautic-patches-zoom')) {
                var sticky = doc.createElement('div');
                sticky.className = 'mautic-patches-zoom-sticky';
                sticky.appendChild(makeGroup(previewView));
                container.insertBefore(sticky, container.firstChild);
            }
        }

        var style = doc.createElement('style');
        style.textContent = '.mautic-patches-zoom{display:inline-flex;align-items:center;gap:4px}'
            + '.mautic-patches-zoom--header{margin-right:8px}'
            // on the Preview the group stays in the top right corner of the box while it scrolls (a zero-height sticky row)
            + '.mautic-patches-zoom-sticky{position:sticky;top:8px;left:0;height:0;z-index:1100;display:flex;align-items:flex-start;justify-content:flex-end;padding-right:16px;pointer-events:none}'
            + '.mautic-patches-zoom-sticky .mautic-patches-zoom{position:static;pointer-events:auto}'
            + '.mautic-patches-zoom .mautic-patches-zoom-button{min-width:0!important;width:auto!important;padding:0 12px!important;justify-content:center!important;text-align:center!important}'
            // 14px, the icon size of the status badges of the contact page (core's button text size)
            + '.mautic-patches-zoom .mautic-patches-zoom-button i{font-size:14px}'
            + '.mautic-patches-zoom .mautic-patches-zoom-button:nth-child(2){width:52px!important;padding:0!important}';
        doc.head.appendChild(style);

        doc.addEventListener('mouseover', function (event) {
            overPreview = !!(event.target && event.target.closest && event.target.closest('#preview-container'));
        }, true);

        // CTRL + wheel over the canvas. Not passive: the browser's own page zoom must be cancelled.
        doc.addEventListener('wheel', function (event) {
            var view = event.ctrlKey ? viewAt(event.target) : null;
            if (!view) { return; }
            event.preventDefault();
            var rect = view.getBoundingClientRect();
            setZoom(wheelZoom(zoom, event.deltaY), {x: event.clientX - rect.left, y: event.clientY - rect.top}, view);
        }, {passive: false, capture: true});

        // CTRL + "+", "-", "0"; not while typing in a field (the key is the field's)
        doc.addEventListener('keydown', function (event) {
            var view = keyView();
            var action = keyAction(event.key);
            var target = event.target || {};
            var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName || '') || target.isContentEditable;
            if (!(event.ctrlKey || event.metaKey) || !action || !view || typing) { return; }
            event.preventDefault();
            if (action === 'reset') { setZoom(1, null, view); } else { setZoom(zoom + (action === 'in' ? STEP : -STEP), null, view); }
        }, true);

        // the canvas is made when the builder opens or the campaign page loads: it starts at 100% for jsPlumb, so the current zoom is
        // given again. A campaign page is another campaign: its Preview starts at 100%.
        function run() {
            addButtons();
            var prepare = Mautic.prepareCampaignCanvas;
            if (prepare && !prepare.mauticPatchesZoom) {
                var wrapped = function () {
                    var result = prepare.apply(this, arguments);
                    if (doc.getElementById('preview-container')) { zoom = 1; }
                    addButtons(); // the Preview ajax replaces its box: the group is put back
                    apply();

                    return result;
                };
                wrapped.mauticPatchesZoom = true;
                Mautic.prepareCampaignCanvas = wrapped;
            }
        }

        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        run();
    }

    api.install = install;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesCanvasZoomApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
