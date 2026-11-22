/**
 * The status badges of the segment and campaign lists, copied to the details page of one segment or campaign: circles left of the Editar
 * button, the same height as the round buttons beside it (the look is the contact page's, see contact-detail-badges.js). A segment shows
 * Ativo / Inativo, then the flags of its list row (global, filters, not rebuilt, slow build); a campaign shows it and, when it lets its contacts enter again, the reentry badge. Informative only: the status is changed in
 * the Edit form or from the list menu. The state comes from a hidden marker that SegmentCampaignDetailStatusSubscriber prints with the page
 * (data-status, data-restart), so nothing is guessed from the labels of core. The page is loaded over ajax: the marker is read again whenever
 * the page changes. Loaded after list-status-toggle.js; adds `installSegmentCampaignDetail` to its api.
 */
(function (root) {
    'use strict';

    var list = typeof module !== 'undefined' && module.exports ? require('./list-status-toggle.js') : root.mauticPatchesListStatusApi;
    if (!list) { return; }

    var BOX_CLASS = 'patches-details-badges';

    /** Whether a path is the details page of one segment or campaign (Mautic may be served under a subdirectory or index.php). */
    function isDetailsPath(path) {
        return /\/s\/(?:segments|campaigns)\/view\/\d+$/.test(path);
    }

    /** What the marker of the page says: {status: 'published' | 'unpublished', restart: bool}, or null when the page has no marker. */
    function readMarker(doc) {
        var marker = doc.querySelector('.patches-detail-status');
        if (!marker) { return null; }

        var flags = marker.getAttribute('data-flags');

        return {
            status: marker.getAttribute('data-status') === 'published' ? 'published' : 'unpublished',
            restart: marker.getAttribute('data-restart') === '1',
            flags: flags ? flags.split(',') : [],
            rebuild: marker.getAttribute('data-rebuild-hours') || '',
            slow: marker.getAttribute('data-build-seconds') || '',
            // a segment only (the contacts badge and the "Updated on" line, see SegmentCampaignDetailStatusSubscriber): null for a campaign
            segment: marker.getAttribute('data-segment-id') ? {
                id: marker.getAttribute('data-segment-id'),
                count: parseInt(marker.getAttribute('data-count'), 10) || 0,
                countText: marker.getAttribute('data-count-text') || '',
                countUrl: marker.getAttribute('data-count-url') || '',
                builtText: marker.getAttribute('data-built-text') || '',
                updatingText: marker.getAttribute('data-updating-text') || ''
            } : null
        };
    }

    /** The badges of the state, as the arguments of list.extraBadge, in order: the status first, then the reentry one. */
    function badgesOf(marker, cfg) {
        var v = list.view(marker.status, cfg);
        var badges = [{channel: 'status', icon: v.labelIcon, text: v.labelText, modifier: marker.status === 'published' ? 'production' : 'failed'}];
        if (marker.restart) { badges.push({channel: 'restart'}); }
        list.SEGMENT_FLAGS.forEach(function (f) {
            if (marker.flags.indexOf(f.key) === -1) { return; }
            var count = f.key === 'rebuild' ? marker.rebuild : marker.slow;
            badges.push({icon: f.icon, text: f.holder ? cfg[f.word].replace('%count%', count) : cfg[f.word], modifier: f.modifier, wide: !!f.holder});
        });

        return badges;
    }

    /**
     * The contacts badge of the segment list (a.col-count: translucent blue with contacts, gray when empty), left of the status badges and as tall
     * as they are. A link to the contacts of the segment, like in the list.
     */
    function countBadge(doc, seg) {
        var a = doc.createElement('a');
        a.className = 'patches-count-badge patches-badge ' + (seg.count > 0 ? 'patches-badge--info' : 'patches-badge--neutral');
        a.setAttribute('data-segment-id', seg.id);
        a.setAttribute('data-updating-text', seg.updatingText);
        a.setAttribute('data-toggle', 'ajax');
        a.setAttribute('href', seg.countUrl);
        a.textContent = seg.countText;

        return a;
    }

    /** The "Updated on ..." line of the segment list under the toolbar (14px, right-aligned); an older one is replaced. */
    function placeUpdatedLine(doc, bar, seg) {
        var old = doc.querySelector('.patches-segment-updated');
        if (old && old.parentNode) { old.parentNode.removeChild(old); }
        // last thing of the page header (a block under the row of title, badges and buttons): inside the toolbar it would be a flex item squeezing the buttons
        var header = bar.closest ? bar.closest('.page-header') : null;
        if (!seg || !(header || bar.parentNode)) { return; }

        var line = doc.createElement('div');
        line.className = 'patches-segment-updated text-muted';
        line.setAttribute('data-segment-id', seg.id);
        line.setAttribute('data-updating-text', seg.updatingText);
        line.textContent = seg.builtText;
        (header || bar.parentNode).appendChild(line);
    }

    function signature(marker) {
        var seg = marker.segment;

        return [marker.status, marker.restart ? '1' : '0', marker.flags.join('+'), marker.rebuild, marker.slow, seg ? [seg.id, seg.count, seg.builtText].join('|') : ''].join(':');
    }

    /**
     * Puts the badges as the first thing of the toolbar of the page, and redraws them when the state changes. Returns whether it wrote the
     * box; a box that is already right is not touched, so the observer that calls this does not loop.
     */
    function addDetailBadges(doc, cfg) {
        // not while the page is still being parsed: the toolbar may be half there (the setup runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        var bar = doc.querySelector('#toolbar .std-toolbar');
        var marker = bar && isDetailsPath(doc.location.pathname) ? readMarker(doc) : null;
        if (!marker) { return false; }

        var sign = signature(marker), old = bar.querySelector('.' + BOX_CLASS);
        if (old && old.getAttribute('data-state') === sign) { return false; }
        if (old) { old.parentNode.removeChild(old); }

        var box = doc.createElement('div');
        box.className = BOX_CLASS;
        box.setAttribute('data-state', sign);
        badgesOf(marker, cfg).forEach(function (b) {
            box.appendChild(b.channel === 'restart' ? list.restartBadge(doc, cfg.restart) : list.extraBadge(doc, b));
        });
        if (marker.segment) { box.insertBefore(countBadge(doc, marker.segment), box.firstChild); }
        bar.insertBefore(box, bar.firstChild);
        placeUpdatedLine(doc, bar, marker.segment);
        // an Update that is running (SegmentUpdateLockSubscriber) owns the badge and the line until the page reloads
        if (root.mauticPatchesSegmentDecorate) { root.mauticPatchesSegmentDecorate(); }

        return true;
    }

    function installSegmentCampaignDetail($, Mautic, cfg) {
        if (root.mauticPatchesSegmentCampaignDetail) { return; }
        root.mauticPatchesSegmentCampaignDetail = true;

        root.mauticPatchesStyles = root.mauticPatchesStyles || {};
        if (!root.mauticPatchesStyles[cfg.style]) {
            root.mauticPatchesStyles[cfg.style] = true;
            var style = root.document.createElement('style');
            style.textContent = cfg.style;
            root.document.head.appendChild(style);
        }

        var draw = function () { addDetailBadges(root.document, cfg); }, pending = false;
        draw();
        root.document.addEventListener('DOMContentLoaded', draw);
        // the page changes by ajax (a click on a segment or campaign): draw again, once per frame
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; draw(); });
        }).observe(root.document.body || root.document.documentElement, {childList: true, subtree: true});
    }

    list.installSegmentCampaignDetail = installSegmentCampaignDetail;
    var api = {countBadge: countBadge, isDetailsPath: isDetailsPath, readMarker: readMarker, badgesOf: badgesOf, addDetailBadges: addDetailBadges, installSegmentCampaignDetail: installSegmentCampaignDetail};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentCampaignDetailApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
