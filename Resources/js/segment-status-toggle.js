/*
 * Activate / deactivate a segment (and, in installCampaign, installUser and installHsm, a campaign, a user and an HSM template) from the three-dots menu of the segment list. The status is shown by a label
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
            labelClass: active ? 'label-success' : 'label-danger',
            labelText:  active ? cfg.active : cfg.inactive,
            action:     active ? 'deactivate' : 'activate',
            actionText: active ? cfg.deactivate : cfg.activate,
            actionIcon: active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'
        };
    }

    function apply($, id, status, cfg) {
        var v = view(status, cfg);
        $('.segment-status-label[data-segment-id="' + id + '"]')
            .removeClass('label-success label-danger label-gray').addClass(v.labelClass)
            .attr('data-status', status).text(v.labelText);
        $('a[data-segment-status-action][data-segment-id="' + id + '"]').each(function () {
            var $link = $(this);
            $link.attr('data-segment-status-action', v.action).attr('aria-label', v.actionText);
            $link.find('span').first().text(v.actionText);
            $link.find('i').first().attr('class', v.actionIcon);
        });
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
        var labels = doc.querySelectorAll('.segment-status-label:not([data-placed])');
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

    /** The width, in px, of a label with the text of Inativo; sets --segment-status-width. 0 when it cannot be measured (hidden page). */
    function measure(doc, cfg) {
        var body = doc.body;
        if (!body) { return 0; }
        var probe = doc.createElement('span');
        probe.className = 'label label-danger segment-status-label';
        probe.setAttribute('data-placed', '1');
        probe.style.cssText = 'position:absolute;visibility:hidden;min-width:0;left:-9999px';
        probe.textContent = cfg.inactive;
        body.appendChild(probe);
        var width = probe.getBoundingClientRect().width;
        body.removeChild(probe);
        if (width > 0) { doc.documentElement.style.setProperty('--segment-status-width', width + 'px'); }

        return width;
    }

    /** What both lists share, once per page: the style of the list, the width of the labels and the placing of the labels. */
    function setup(cfg) {
        var style = root.document.createElement('style');
        style.textContent = cfg.style;
        root.document.head.appendChild(style);

        if (root.mauticPatchesStatusLabels) { return; }
        root.mauticPatchesStatusLabels = true;

        measure(root.document, cfg);
        if (root.document.fonts && root.document.fonts.ready) { root.document.fonts.ready.then(function () { measure(root.document, cfg); }); }

        // the labels of a list that is in the page, and of every list loaded later
        place(root.document);
        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; place(root.document); });
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

            var $label = $('.segment-status-label[data-segment-id="' + id + '"]');
            var before = $label.attr('data-status') || 'unpublished';
            $label.removeClass('label-success label-danger').addClass('label-gray')
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
            $(label).removeClass('label-success label-danger').addClass('label-gray')
                .html('<i class="ri-loader-3-line ri-spin" aria-hidden="true"></i>').attr('data-status', 'busy');
            return;
        }
        if (label.getAttribute('data-status') === status) { return; }
        var v = view(status, cfg);
        $(label).removeClass('label-success label-danger label-gray').addClass(v.labelClass).attr('data-status', status).text(v.labelText);
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

    /** Brings the label and the menu item of every campaign row to the state of its core icon. */
    function syncCampaigns($, cfg) {
        var rows = root.document.querySelectorAll('tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], label = row.querySelector('.segment-status-label[data-campaign-id]');
            var icon = label && iconOf(row, 'campaign');
            if (!icon) { continue; }
            var id = label.getAttribute('data-campaign-id');
            paint($, label, icon, icon.getAttribute('data-status') === 'published' ? 'published' : 'unpublished',
                $('a[data-campaign-status-action][data-campaign-id="' + id + '"]'), 'data-campaign-status-action', cfg);
        }
    }

    function installCampaign($, Mautic, cfg) {
        if (!$ || root.mauticPatchesCampaignStatus) { return; }
        root.mauticPatchesCampaignStatus = true;
        installIconDriven($, cfg, 'campaign', 'data-campaign-status-action', syncCampaigns);
    }

    /**
     * Brings the label and the menu item of every row of a list that has no hook for the label (the user and the HSM template lists) to
     * the state of its core icon: the label is made here, at the start of the name (`nameSelector`, inside the row). The row without
     * an icon (the logged user) is always active.
     */
    function syncNamedRows($, cfg, tableId, kind, attr, nameSelector) {
        var table = root.document.getElementById(tableId);
        if (!table) { return; }
        var rows = table.querySelectorAll('tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], icon = iconOf(row, kind), name = row.querySelector(nameSelector);
            if (!name) { continue; }
            var label = row.querySelector('.segment-status-label');
            if (!label) {
                label = root.document.createElement('span');
                label.className = 'label segment-status-label';
                label.setAttribute('data-placed', '1');
                label.setAttribute('data-status', '');
                name.insertBefore(label, name.firstChild);
            }
            paint($, label, icon, icon && icon.getAttribute('data-status') !== 'published' ? 'unpublished' : 'published',
                $(row).find('a[' + attr + ']'), attr, cfg);
        }
    }

    function syncUsers($, cfg) {
        syncNamedRows($, cfg, 'userTable', 'user', 'data-user-status-action', 'td:nth-child(3) > div');
    }

    function syncHsm($, cfg) {
        syncNamedRows($, cfg, 'hsmTemplateTable', 'n8ndispatch-hsmtemplate', 'data-hsm-status-action', 'td:nth-child(2) > div');
    }

    function installUser($, Mautic, cfg) {
        if (!$ || root.mauticPatchesUserStatus) { return; }
        root.mauticPatchesUserStatus = true;
        installIconDriven($, cfg, 'user', 'data-user-status-action', syncUsers);
        syncUsers($, cfg);
    }

    function installHsm($, Mautic, cfg) {
        if (!$ || root.mauticPatchesHsmStatus) { return; }
        root.mauticPatchesHsmStatus = true;
        installIconDriven($, cfg, 'n8ndispatch-hsmtemplate', 'data-hsm-status-action', syncHsm);
        syncHsm($, cfg);
    }

    var api = {install: install, installCampaign: installCampaign, installUser: installUser, installHsm: installHsm, statusOf: statusOf, view: view, place: place, measure: measure, withoutAlias: withoutAlias};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
