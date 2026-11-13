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
            slow: marker.getAttribute('data-build-seconds') || ''
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

    function signature(marker) {
        return [marker.status, marker.restart ? '1' : '0', marker.flags.join('+'), marker.rebuild, marker.slow].join(':');
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
        bar.insertBefore(box, bar.firstChild);

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
    var api = {isDetailsPath: isDetailsPath, readMarker: readMarker, badgesOf: badgesOf, addDetailBadges: addDetailBadges, installSegmentCampaignDetail: installSegmentCampaignDetail};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentCampaignDetailApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
