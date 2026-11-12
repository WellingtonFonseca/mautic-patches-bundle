/**
 * The Email / SMS / HSM badges of the contact list's Status column, copied to the contact page: three circles left of the Editar button, the
 * same height as the round buttons beside it. The state is read from the Do Not Contact labels that core prints on the page (the right column,
 * Lead/_dnc_large.html.twig), so no query is added. The look and the colors are the list's (list-status-toggle.js, contactBadges); this file only
 * places them. It is loaded after list-status-toggle.js and adds `installContactDetail` to its api.
 */
(function (root) {
    'use strict';

    var list = typeof module !== 'undefined' && module.exports ? require('./list-status-toggle.js') : root.mauticPatchesListStatusApi;
    if (!list) { return; }

    var BOX_CLASS = 'patches-contact-badges';

    /** Whether a path is the page of one contact (Mautic may be served under a subdirectory or index.php). */
    function isContactViewPath(path) {
        return /\/s\/contacts\/view\/\d+$/.test(path);
    }

    /**
     * The Do Not Contact channels of the contact: core prints one label per blocked channel, "Do Not Contact: EMAIL" (or "Bounced: SMS"),
     * so the channel is the last word of the label, in any language.
     */
    function blockedChannels(doc) {
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
     * Puts the three badges as the first thing of the toolbar of the contact page, and redraws them when a Do Not Contact label is added or
     * removed (core removes it by ajax). Returns whether it wrote the box; a box that is already right is not touched, so the observer that calls this does not loop.
     */
    function addContactDetailBadges(doc, cfg) {
        // not while the page is still being parsed: the toolbar may be half there (the setup runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        var bar = doc.querySelector('#toolbar .std-toolbar');
        if (!bar || !isContactViewPath(doc.location.pathname)) { return false; }

        var badges = list.contactBadges(blockedChannels(doc), cfg), sign = signature(badges), old = bar.querySelector('.' + BOX_CLASS);
        if (old && old.getAttribute('data-state') === sign) { return false; }
        if (old) { old.parentNode.removeChild(old); }

        var box = doc.createElement('div');
        box.className = BOX_CLASS;
        box.setAttribute('data-state', sign);
        for (var k = 0; k < badges.length; k++) { box.appendChild(list.extraBadge(doc, badges[k])); }
        bar.insertBefore(box, bar.firstChild);

        return true;
    }

    function installContactDetail($, Mautic, cfg) {
        if (root.mauticPatchesContactDetail) { return; }
        root.mauticPatchesContactDetail = true;

        root.mauticPatchesStyles = root.mauticPatchesStyles || {};
        if (!root.mauticPatchesStyles[cfg.style]) {
            root.mauticPatchesStyles[cfg.style] = true;
            var style = root.document.createElement('style');
            style.textContent = cfg.style;
            root.document.head.appendChild(style);
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

    list.installContactDetail = installContactDetail;
    var api = {isContactViewPath: isContactViewPath, blockedChannels: blockedChannels, addContactDetailBadges: addContactDetailBadges, installContactDetail: installContactDetail};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesContactDetailApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
