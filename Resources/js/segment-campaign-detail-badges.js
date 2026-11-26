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

    /** Whether a path is the details page of one segment, campaign, email, Custom Object or Custom Item (Mautic may be served under a subdirectory or index.php). */
    function isDetailsPath(path) {
        return /\/s\/(?:segments|campaigns|emails)\/view\/\d+$|\/s\/custom\/object\/(?:view\/\d+|\d+\/item\/view\/\d+)$/.test(path);
    }

    /** What the marker of the page says: {status: 'published' | 'unpublished', restart: bool}, or null when the page has no marker. */
    function readMarker(doc) {
        var marker = doc.querySelector('.patches-detail-status');
        if (!marker) { return null; }

        var flags = marker.getAttribute('data-flags'), emailFlags = marker.getAttribute('data-email-flags');

        return {
            status: marker.getAttribute('data-status') === 'published' ? 'published' : 'unpublished',
            restart: marker.getAttribute('data-restart') === '1',
            flags: flags ? flags.split(',') : [],
            // an email only (the type and the flags of its list row, see EmailStatusSubscriber): emailType is '' for a segment or campaign
            emailType: marker.getAttribute('data-email-type') || '',
            emailFlags: emailFlags ? emailFlags.split(',') : [],
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
        if (marker.emailType) {
            // the same badges and order as the email list: type, then A/B test, translation, draft
            var type = list.EMAIL_TYPES[marker.emailType];
            badges.push({icon: type.icon, text: cfg[type.word], modifier: 'emailtype'});
            list.EMAIL_FLAGS.forEach(function (f) {
                if (marker.emailFlags.indexOf(f.key) !== -1) { badges.push({icon: f.icon, text: cfg[f.word], modifier: f.modifier}); }
            });
        }
        list.SEGMENT_FLAGS.forEach(function (f) {
            if (marker.flags.indexOf(f.key) === -1) { return; }
            var count = f.key === 'rebuild' ? marker.rebuild : marker.slow;
            badges.push({icon: f.icon, text: f.holder ? cfg[f.word].replace('%count%', count) : cfg[f.word], modifier: f.modifier, wide: !!f.holder});
        });
        // only the details page has it: the segment has the Distinct setting on (orange)
        if (marker.flags.indexOf('distinct') !== -1) { badges.push({icon: 'ri-fingerprint-line', text: cfg.distinct, modifier: 'distinct'}); }

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

    /** The box of circles of a marker: the contacts badge (a segment), then the status and the flags. */
    function buildBox(doc, marker, cfg) {
        var box = doc.createElement('div');
        box.className = BOX_CLASS;
        badgesOf(marker, cfg).forEach(function (b) {
            box.appendChild(b.channel === 'restart' ? list.restartBadge(doc, cfg.restart) : list.extraBadge(doc, b));
        });
        if (marker.segment) { box.insertBefore(countBadge(doc, marker.segment), box.firstChild); }

        return box;
    }

    function signature(marker) {
        var seg = marker.segment;

        return [marker.status, marker.restart ? '1' : '0', marker.flags.join('+'), marker.emailType + marker.emailFlags.join('+'), marker.rebuild, marker.slow, seg ? [seg.id, seg.count, seg.builtText].join('|') : ''].join(':');
    }

    /**
     * Draws the badges of a segment header component (Segment/header.html.twig: title, hidden marker, "Updated on" line), shared by the details
     * modal and the segment page: the box goes right after the title. A box that is already right is not touched. Returns whether the host
     * holds a marker (segment or campaign). The title and the line are printed by the twig; only the badges need the script.
     */
    function renderHeader(doc, host, cfg) {
        var marker = readMarker(host);
        if (!marker) { return false; }

        var sign = signature(marker), old = host.querySelector('.' + BOX_CLASS);
        if (old && old.getAttribute('data-state') === sign) { return false; }
        if (old) { old.parentNode.removeChild(old); }
        var box = buildBox(doc, marker, cfg);
        box.setAttribute('data-state', sign);
        var title = host.querySelector('.patches-modal-name');
        host.insertBefore(box, title ? title.nextSibling : host.firstChild);
        if (root.mauticPatchesSegmentDecorate) { root.mauticPatchesSegmentDecorate(); }

        return true;
    }

    /** The details modal: the component is in the body, nothing to move. */
    function renderModalHead(doc, cfg) {
        var host = doc.querySelector('#MauticSharedModal .patches-segment-header') || doc.querySelector('.patches-segment-header');
        if (!host) { return false; }
        renderHeader(doc, host, cfg);
        // the contacts badge leaves the modal's page: close the modal, as the Edit and Details buttons do
        var link = host.querySelector('.patches-count-badge');
        if (link && !link.__closes) {
            link.__closes = true;
            link.addEventListener('click', function () { if (root.mQuery) { root.mQuery('#MauticSharedModal').modal('hide'); } });
        }

        return true;
    }

    /** The tab title as a full load gives it ("<PREFIX - Name> | Mautic"); core's ajax title joins the text of every h1.page-header-title and adds "View |". */
    function setPageTitle(doc, title) {
        var want = title.trim() + ' | Mautic';
        if (doc.title !== want) { doc.title = want; }
    }

    /**
     * The segment page: the component takes the place of core's heading (h1.page-header-title) and the document title follows it, as the title
     * script of SegmentTitlePrefixSubscriber would. Returns whether it placed a component.
     */
    function placePageHeader(doc, cfg) {
        var host = doc.querySelector('.patches-segment-header:not([data-placed]):not(#MauticSharedModal *)');
        var core = host ? Array.prototype.filter.call(doc.querySelectorAll('h1.page-header-title'), function (h) { return !host.contains(h); })[0] : null;
        if (!core || !core.parentNode) { return false; }

        var title = host.querySelector('.patches-modal-name').textContent, old = core.textContent.trim();
        host.setAttribute('data-placed', '1');
        // the email page: core's tags (type, A/B test, translation) sit beside its status label, and the badges replace them
        core.parentNode.replaceChild(host, core);
        Array.prototype.forEach.call(doc.querySelectorAll('.patches-email-header ~ .tag'), function (t) { t.parentNode.removeChild(t); });
        // segment and campaign pages: core's own Ativo label (div.mt-md.d-flex.gap-xs with the publishstatus_pulse dot) repeats the badge of the component
        Array.prototype.forEach.call(doc.querySelectorAll('.publishstatus_pulse'), function (dot) {
            var block = dot.closest('div.mt-md.d-flex.gap-xs');
            if (block && !host.contains(block) && block.parentNode) { block.parentNode.removeChild(block); }
        });
        if (old) { setPageTitle(doc, title); }
        renderHeader(doc, host, cfg);

        return true;
    }

    /**
     * Puts the badges as the first thing of the toolbar of the page, and redraws them when the state changes. Returns whether it wrote the
     * box; a box that is already right is not touched, so the observer that calls this does not loop.
     */
    function addDetailBadges(doc, cfg) {
        // not while the page is still being parsed: the toolbar may be half there (the setup runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        // a segment or campaign: the header component (placePageHeader)
        if (isDetailsPath(doc.location.pathname) && doc.querySelector('.patches-segment-header')) { return placePageHeader(doc, cfg); }
        var bar = doc.querySelector('#toolbar .std-toolbar');
        var marker = bar && isDetailsPath(doc.location.pathname) ? readMarker(doc) : null;
        if (!marker) { return false; }

        var sign = signature(marker), old = bar.querySelector('.' + BOX_CLASS);
        if (old && old.getAttribute('data-state') === sign) { return false; }
        if (old) { old.parentNode.removeChild(old); }

        var box = buildBox(doc, marker, cfg);
        box.setAttribute('data-state', sign);
        bar.insertBefore(box, bar.firstChild);
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

        // core builds the title after an ajax load from the text of the headings: give it back to the component once core is done
        if (Mautic && typeof Mautic.generatePageTitle === 'function' && !Mautic.generatePageTitle.patchedDetail) {
            var coreTitle = Mautic.generatePageTitle;
            Mautic.generatePageTitle = function () {
                var out = coreTitle.apply(this, arguments), host = root.document.querySelector('.patches-segment-header[data-placed] .patches-modal-name');
                if (host && isDetailsPath(root.document.location.pathname)) { setPageTitle(root.document, host.textContent); }
                return out;
            };
            Mautic.generatePageTitle.patchedDetail = true;
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
    list.installSegmentModalHead = function ($, Mautic, cfg) {
        if (!root.document.querySelector('.patches-segment-header')) { return; }
        root.mauticPatchesStyles = root.mauticPatchesStyles || {};
        if (!root.mauticPatchesStyles[cfg.style]) {
            root.mauticPatchesStyles[cfg.style] = true;
            var style = root.document.createElement('style');
            style.textContent = cfg.style;
            root.document.head.appendChild(style);
        }
        renderModalHead(root.document, cfg);
    };
    var api = {countBadge: countBadge, isDetailsPath: isDetailsPath, readMarker: readMarker, buildBox: buildBox, renderModalHead: renderModalHead, renderHeader: renderHeader, placePageHeader: placePageHeader, badgesOf: badgesOf, addDetailBadges: addDetailBadges, installSegmentCampaignDetail: installSegmentCampaignDetail};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentCampaignDetailApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
