/**
 * The Email / SMS / HSM badges of the contact list's Status column, on the contact page: the header component (Contact/header.html.twig) takes the place of
 * core's heading and holds the three circles after the title. The state is read from the Do Not Contact labels that core prints on the page (the right
 * column, Lead/_dnc_large.html.twig), so no query is added. The look and the colors are the list's (list-status-toggle.js, contactBadges). It is loaded
 * after list-status-toggle.js and adds `installContactDetail` and `installContactModalHead` to its api.
 */
(function (root) {
    'use strict';

    var list = typeof module !== 'undefined' && module.exports ? require('./list-status-toggle.js') : root.mauticPatchesListStatusApi;
    if (!list) { return; }

    /** Whether a path is the page of one contact (Mautic may be served under a subdirectory or index.php). */
    function isContactViewPath(path) {
        return /\/s\/contacts\/view\/\d+$/.test(path);
    }

    /**
     * The Do Not Contact channels of the contact: core prints one label per blocked channel, "Do Not Contact: EMAIL" (or "Bounced: SMS"),
     * so the channel is the last word of the label, in any language.
     */
    function pageBlockedChannels(doc) {
        var labels = doc.querySelectorAll('[id^="bounceLabel"] .label'), blocked = {};
        for (var k = 0; k < labels.length; k++) {
            var word = (labels[k].textContent || '').trim().split(/\s+/).pop().toLowerCase();
            if (word) { blocked[word] = true; }
        }

        return blocked;
    }

    /** The badges as one string, to tell whether the box on the page is up to date. */
    function signature(badges) {
        return badges.map(function (b) { return b.channel + ':' + b.modifier; }).join(',');
    }

    /**
     * Draws the three badges into the header component, right after the title; a box that is already right is not touched, so the observer that
     * calls this does not loop.
     */
    function drawBadges(doc, host, blocked, cfg) {
        var badges = list.contactBadges(blocked, cfg), sign = signature(badges), old = host.querySelector('.patches-details-badges');
        if (old && old.getAttribute('data-state') === sign) { return false; }
        if (old) { old.parentNode.removeChild(old); }

        var box = doc.createElement('div');
        box.className = 'patches-details-badges';
        box.setAttribute('data-state', sign);
        for (var k = 0; k < badges.length; k++) { box.appendChild(list.extraBadge(doc, badges[k])); }
        var title = host.querySelector('.patches-modal-name');
        host.insertBefore(box, title ? title.nextSibling : host.firstChild);

        return true;
    }

    function addStyle(cfg) {
        root.mauticPatchesStyles = root.mauticPatchesStyles || {};
        if (!root.mauticPatchesStyles[cfg.style]) {
            root.mauticPatchesStyles[cfg.style] = true;
            var style = root.document.createElement('style');
            style.textContent = cfg.style;
            root.document.head.appendChild(style);
        }
    }

    /** The tab title as a full load gives it ("<Name> | Mautic"); core's ajax title joins the text of every heading and adds "View |". */
    function setPageTitle(doc, title) {
        var want = title.trim() + ' | Mautic';
        if (doc.title !== want) { doc.title = want; }
    }

    /**
     * The contact page: the header component takes the place of core's heading (h1.page-header-title) and the document title follows it, as on the
     * segment and campaign pages. The badges follow the Do Not Contact labels core prints (core removes one by ajax). Returns whether it changed the page.
     */
    function addContactDetailBadges(doc, cfg) {
        // not while the page is still being parsed: the heading may be half there (the setup runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        if (!isContactViewPath(doc.location.pathname)) { return false; }

        var changed = false, host = doc.querySelector('.patches-contact-header:not([data-placed]):not(#MauticSharedModal *)');
        if (host) {
            var core = Array.prototype.filter.call(doc.querySelectorAll('h1.page-header-title'), function (h) { return !host.contains(h); })[0];
            if (!core || !core.parentNode) { return false; }
            host.setAttribute('data-placed', '1');
            core.parentNode.replaceChild(host, core);
            changed = true;
        }

        var placed = doc.querySelector('.patches-contact-header[data-placed]');
        if (placed) { setPageTitle(doc, placed.querySelector('.patches-modal-name').textContent); }

        return placed ? drawBadges(doc, placed, pageBlockedChannels(doc), cfg) || changed : changed;
    }

    function installContactDetail($, Mautic, cfg) {
        if (root.mauticPatchesContactDetail) { return; }
        root.mauticPatchesContactDetail = true;
        addStyle(cfg);

        // core builds the title after an ajax load from the text of the headings: give it back to the component once core is done
        if (Mautic && typeof Mautic.generatePageTitle === 'function' && !Mautic.generatePageTitle.patchedContact) {
            var coreTitle = Mautic.generatePageTitle;
            Mautic.generatePageTitle = function () {
                var out = coreTitle.apply(this, arguments), host = root.document.querySelector('.patches-contact-header[data-placed] .patches-modal-name');
                if (host && isContactViewPath(root.document.location.pathname)) { setPageTitle(root.document, host.textContent); }
                return out;
            };
            Mautic.generatePageTitle.patchedContact = true;
        }

        var draw = function () { addContactDetailBadges(root.document, cfg); }, pending = false;
        draw();
        root.document.addEventListener('DOMContentLoaded', draw);
        // the page changes by ajax (a click on a contact, the removal of a Do Not Contact label): draw again, once per frame
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; draw(); });
        }).observe(root.document.body || root.document.documentElement, {childList: true, subtree: true});
    }

    /**
     * The header component of the contact "Ver" modal (Contact/header.html.twig): the box with the three badges goes right after the title, drawn
     * from the Do Not Contact channels the hidden marker holds. A box that is already right is not touched.
     */
    function renderContactModalHead(doc, cfg) {
        var host = doc.querySelector('#MauticSharedModal .patches-contact-header') || doc.querySelector('.patches-contact-header');
        var marker = host && host.querySelector('.patches-detail-status');
        if (!marker) { return false; }

        var blocked = {};
        (marker.getAttribute('data-blocked') || '').split(',').forEach(function (c) { if (c) { blocked[c] = true; } });

        return drawBadges(doc, host, blocked, cfg);
    }

    function installContactModalHead($, Mautic, cfg) {
        if (!root.document.querySelector('.patches-contact-header')) { return; }
        addStyle(cfg);
        renderContactModalHead(root.document, cfg);
    }

    list.installContactDetail = installContactDetail;
    list.installContactModalHead = installContactModalHead;
    var api = {isContactViewPath: isContactViewPath, pageBlockedChannels: pageBlockedChannels, addContactDetailBadges: addContactDetailBadges, renderContactModalHead: renderContactModalHead, installContactModalHead: installContactModalHead, installContactDetail: installContactDetail};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesContactDetailApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
