/*
 * Activate / deactivate a segment (and, in installCampaign, installUser and installEmail, a campaign, a user and an email) from the three-dots menu of the segment list. The status is shown by a label
 * (SegmentStatusSubscriber: "Ativo" green, "Inativo" red) that is only informative; the small toggle icon next to the name,
 * which was easy to hit by mistake, is hidden.
 *
 * The menu item (`a[data-segment-status-action]`, activate or deactivate) sends the same request core's icon sends
 * (Mautic.togglePublishStatus in 10.entity.js: action=togglePublishStatus, model=lead.list), so the server checks the
 * permission as it always did. While it runs the label is a spinning loader. The answer (`statusHtml`, the new icon) tells
 * the new status through its data-status; the label and the menu item then change to it. An error puts them back and
 * shows core's ajax error. Listeners on the document: a list loaded by ajax is covered.
 */
(function (root) {
    'use strict';

    var STATES = 'patches-badge--production patches-badge--failed patches-badge--pending';

    /** The status the answer says: published or not (unpublished, expired and pending are "not active"). */
    function statusOf(response) {
        var html = response && response.statusHtml;
        var match = typeof html === 'string' ? html.match(/data-status="([a-z]+)"/) : null;

        return match && match[1] === 'published' ? 'published' : 'unpublished';
    }

    /** What the label and the menu item show for a status. `cfg`: the texts. */
    function view(status, cfg) {
        var active = status === 'published';

        return {
            labelClass: active ? 'patches-badge--production' : 'patches-badge--failed',
            labelText:  active ? cfg.active : cfg.inactive,
            action:     active ? 'deactivate' : 'activate',
            actionText: active ? cfg.deactivate : cfg.activate,
            actionIcon: active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'
        };
    }

    function apply($, id, status, cfg) {
        var v = view(status, cfg);
        $('.patches-status-label[data-segment-id="' + id + '"]')
            .removeClass(STATES).addClass(v.labelClass)
            .attr('data-status', status).text(v.labelText);
        $('a[data-segment-status-action][data-segment-id="' + id + '"]').each(function () {
            var $link = $(this);
            $link.attr('data-segment-status-action', v.action).attr('aria-label', v.actionText);
            $link.find('span').first().text(v.actionText);
            $link.find('i').first().attr('class', v.actionIcon);
        });
        toggleUpdate(root.document, 'segment-rebuild', id, status);
    }

    /** The text without " (alias)": the exact alias in parentheses (the last one), with the spaces before it. */
    function withoutAlias(text, alias) {
        if (!alias) { return text; }
        var needle = '(' + alias + ')', at = text.lastIndexOf(needle);
        if (at === -1) { return text; }

        return text.slice(0, at).replace(/[ \t]+$/, '') + text.slice(at + needle.length);
    }

    /** Takes "(alias)" off the text nodes of a block (deep). */
    function stripAlias(node, alias) {
        for (var child = node.firstChild; child; child = child.nextSibling) {
            if (child.nodeType === 3) {
                var cleaned = withoutAlias(child.nodeValue, alias);
                if (cleaned !== child.nodeValue) { child.nodeValue = cleaned; }
            } else if (child.nodeType === 1) {
                stripAlias(child, alias);
            }
        }
    }

    /** Moves the labels that are not at the start of their block yet; returns how many it moved. */
    function place(doc) {
        var moved = 0;
        var labels = doc.querySelectorAll('.patches-status-label:not([data-placed])');
        for (var k = 0; k < labels.length; k++) {
            var label = labels[k], parent = label.parentNode;
            // the campaign list prints the hook inside the name link: the label goes to the start of the block, outside the link
            if (parent && parent.tagName === 'A') { parent = parent.parentNode; }
            if (parent && parent.firstChild !== label) { parent.insertBefore(label, parent.firstChild); moved++; }
            if (parent && label.getAttribute('data-alias')) { stripAlias(parent, label.getAttribute('data-alias')); }
            label.setAttribute('data-placed', '1');
        }

        return moved;
    }

    /**
     * The contact count of a segment row (`a.col-count`, "Ver 6 Contatos") is a patches-badge like the status: translucent blue
     * (--info) when the segment has contacts, translucent gray (--neutral) when it is empty. Core prints it as `label label-gray`
     * with size="sm" and fills the text by ajax after the page loads, so this runs on every change.
     */
    function colorCounts(doc) {
        var badges = doc.querySelectorAll('a.col-count');
        for (var k = 0; k < badges.length; k++) {
            var match = /\d+/.exec(badges[k].textContent);
            // core writes "Sem contatos" / "No contacts" for an empty segment: no number means empty
            var filled = !!match && parseInt(match[0], 10) > 0;
            var modifier = filled ? 'patches-badge--info' : 'patches-badge--neutral';
            var other = filled ? 'patches-badge--neutral' : 'patches-badge--info';
            // already right: no DOM write, so no mutation to wake the observers
            if (badges[k].classList.contains(modifier) && !badges[k].classList.contains(other)) { continue; }
            badges[k].removeAttribute('size');
            badges[k].classList.remove('label', 'label-gray', 'label-blue', 'patches-badge--info', 'patches-badge--neutral');
            badges[k].classList.add('patches-badge', modifier);
        }
    }

    /** What both lists share, once per page: the style of the list and the placing of the labels. */
    function setup(cfg) {
        var style = root.document.createElement('style');
        style.textContent = cfg.style;
        root.document.head.appendChild(style);

        if (root.mauticPatchesStatusLabels) { return; }
        root.mauticPatchesStatusLabels = true;

        // the labels of a list that is in the page, and of every list loaded later
        place(root.document);
        colorCounts(root.document);
        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; place(root.document); colorCounts(root.document); });
        }).observe(root.document.body || root.document.documentElement, {childList: true, subtree: true});
    }

    function install($, Mautic, cfg) {
        if (!$ || root.mauticPatchesSegmentStatus) { return; }
        root.mauticPatchesSegmentStatus = true;
        setup(cfg);

        $(root.document).on('click', 'a[data-segment-status-action]', function (event) {
            event.preventDefault();
            var $link = $(this);
            var id    = $link.attr('data-segment-id');
            if ($link.data('busy')) { return; }
            $link.data('busy', true);

            var $label = $('.patches-status-label[data-segment-id="' + id + '"]');
            var before = $label.attr('data-status') || 'unpublished';
            $label.removeClass(STATES).addClass('patches-badge--pending')
                .html('<i class="ri-loader-3-line ri-spin" aria-hidden="true"></i>');

            if (root.MauticVars) { root.MauticVars.liveCache = []; }
            $.ajax({
                url: root.mauticAjaxUrl,
                type: 'POST',
                data: 'action=togglePublishStatus&model=lead.list&id=' + encodeURIComponent(id),
                dataType: 'json',
                success: function (response) {
                    $link.data('busy', false);
                    if (response && response.reload) { Mautic.redirectWithBackdrop(root.location); return; }
                    apply($, id, statusOf(response), cfg);
                },
                error: function (request, textStatus, errorThrown) {
                    $link.data('busy', false);
                    apply($, id, before, cfg);
                    Mautic.processAjaxError(request, textStatus, errorThrown);
                }
            });
        });
    }

    /** The core icon of a row (hidden), whose state tells the status; null when the row has none. */
    function iconOf(row, kind) { return row.querySelector('i[class*="' + kind + '-publish-icon"]'); }

    /**
     * Brings a label and the menu items of its row ($links, whose `attr` holds the action) to the state of core's icon: spinning while
     * core works, then published / not. Nothing is touched when the label already shows the status.
     */
    function paint($, label, icon, status, $links, attr, cfg) {
        if (icon && /\bri-spin\b/.test(icon.className)) {
            $(label).removeClass(STATES).addClass('patches-badge--pending')
                .html('<i class="ri-loader-3-line ri-spin" aria-hidden="true"></i>').attr('data-status', 'busy');
            return;
        }
        if (label.getAttribute('data-status') === status) { return; }
        var v = view(status, cfg);
        $(label).removeClass(STATES).addClass(v.labelClass).attr('data-status', status).text(v.labelText);
        $links.each(function () {
            var $link = $(this);
            $link.attr(attr, v.action).attr('aria-label', v.actionText);
            $link.find('span').first().text(v.actionText);
            $link.find('i').first().attr('class', v.actionIcon);
        });
    }

    /**
     * What the campaign and the user lists share: the menu item (`a[attr]`) clicks core's icon of the row (hidden), so core asks its own
     * confirmation and checks the permission; and the labels follow core's icon (the spin while the request runs, the new icon when it
     * is answered) through a MutationObserver, once per frame. `sync` brings every row of the page to the state of its icon.
     */
    function installIconDriven($, cfg, kind, attr, sync) {
        setup(cfg);

        $(root.document).on('click', 'a[' + attr + ']', function (event) {
            event.preventDefault();
            var row = this.closest('tr'), icon = row && iconOf(row, kind);
            if (icon) { icon.click(); }
        });

        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; sync($, cfg); });
        }).observe(root.document.body || root.document.documentElement, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-status']});
    }

    /**
     * The Update item of a row menu (rendered by the server, hidden for an inactive segment or campaign) is shown while it is active:
     * activating or deactivating from the menu changes only the icon or the label, no new menu comes from the server. `scope` is what
     * holds the links (the row, or the document); `path` is the route of the item (`segment-rebuild`, `campaign-rebuild`).
     */
    function toggleUpdate(scope, path, id, status) {
        var links = scope.querySelectorAll('a[href*="/s/' + path + '/' + id + '"]');
        for (var k = 0; k < links.length; k++) {
            if (status === 'published') { links[k].removeAttribute('hidden'); } else { links[k].setAttribute('hidden', 'hidden'); }
        }
    }

    /** Brings the label and the menu item of every campaign row to the state of its core icon. */
    function syncCampaigns($, cfg) {
        if (!root.document.querySelector('.patches-status-label[data-campaign-id]')) { return; } // not a campaign list: no row to look at
        var rows = root.document.querySelectorAll('tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], label = row.querySelector('.patches-status-label[data-campaign-id]');
            var icon = label && iconOf(row, 'campaign');
            if (!icon) { continue; }
            var id = label.getAttribute('data-campaign-id');
            var status = icon.getAttribute('data-status') === 'published' ? 'published' : 'unpublished';
            paint($, label, icon, status, $('a[data-campaign-status-action][data-campaign-id="' + id + '"]'), 'data-campaign-status-action', cfg);
            if (!/\bri-spin\b/.test(icon.className)) { toggleUpdate(row, 'campaign-rebuild', id, status); }
        }
    }

    function installCampaign($, Mautic, cfg) {
        if (!$ || root.mauticPatchesCampaignStatus) { return; }
        root.mauticPatchesCampaignStatus = true;
        installIconDriven($, cfg, 'campaign', 'data-campaign-status-action', syncCampaigns);
    }

    /**
     * Brings the label and the menu item of every row of a list that has no hook for the label (the user and email lists) to
     * the state of its core icon: the label is made here, at the start of the name (`nameSelector`, inside the row). The row without
     * an icon (the logged user) is always active.
     */
    function syncNamedRows($, cfg, tableSelector, kind, attr, nameSelector) {
        var table = root.document.querySelector(tableSelector);
        if (!table) { return; }
        var rows = table.querySelectorAll('tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], icon = iconOf(row, kind), name = row.querySelector(nameSelector);
            if (!name) { continue; }
            var label = row.querySelector('.patches-status-label');
            if (!label) {
                label = root.document.createElement('span');
                label.className = 'patches-badge patches-status-label';
                label.setAttribute('data-placed', '1');
                label.setAttribute('data-status', '');
                name.insertBefore(label, name.firstChild);
            }
            paint($, label, icon, icon && icon.getAttribute('data-status') === 'unpublished' ? 'unpublished' : 'published',
                $(row).find('a[' + attr + ']'), attr, cfg);
        }
    }

    function syncUsers($, cfg) {
        syncNamedRows($, cfg, '#userTable', 'user', 'data-user-status-action', 'td:nth-child(3) > div');
    }

    function syncEmails($, cfg) {
        syncNamedRows($, cfg, 'table.email-list', 'email', 'data-email-status-action', 'td:nth-child(2) > div');
    }

    function installUser($, Mautic, cfg) {
        if (!$ || root.mauticPatchesUserStatus) { return; }
        root.mauticPatchesUserStatus = true;
        installIconDriven($, cfg, 'user', 'data-user-status-action', syncUsers);
        syncUsers($, cfg);
    }

    function installEmail($, Mautic, cfg) {
        if (!$ || root.mauticPatchesEmailStatus) { return; }
        root.mauticPatchesEmailStatus = true;
        installIconDriven($, cfg, 'email', 'data-email-status-action', syncEmails);
        syncEmails($, cfg);
    }

    var api = {install: install, installCampaign: installCampaign, toggleUpdate: toggleUpdate, installUser: installUser, installEmail: installEmail, statusOf: statusOf, view: view, place: place, colorCounts: colorCounts, withoutAlias: withoutAlias};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesListStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
