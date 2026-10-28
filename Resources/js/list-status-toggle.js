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
            labelIcon:  active ? 'ri-play-fill' : 'ri-stop-fill',
            action:     active ? 'deactivate' : 'activate',
            actionText: active ? cfg.deactivate : cfg.activate,
            actionIcon: active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'
        };
    }

    /**
     * Writes the status into a label. In the Status column of a list (segments, campaigns, users, emails) the label is an icon (play for active, stop for
     * inactive) that opens to show the word on hover (the CSS of CampaignStatusSubscriber, as the n8n badge of the campaign card); the
     * word is also its title and aria-label. Everywhere else it is the word alone.
     */
    function fillLabel(label, v) {
        if (!label.closest || !label.closest('td.col-patches-status')) { label.textContent = v.labelText; return; }
        var doc = label.ownerDocument, icon = doc.createElement('i'), text = doc.createElement('span');
        icon.className = v.labelIcon;
        icon.setAttribute('aria-hidden', 'true');
        text.className = 'patches-icon-badge__text';
        text.textContent = v.labelText;
        label.textContent = '';
        label.appendChild(icon);
        label.appendChild(text);
        label.setAttribute('title', v.labelText);
        label.setAttribute('aria-label', v.labelText);
    }

    /**
     * The badge next to the status of a campaign that lets its contacts enter again (allowRestart): a reload icon, blue, that opens on
     * hover to show the word like the status badge. Informative only, it is not touched after the table is drawn.
     */
    function restartBadge(doc, text) {
        var badge = doc.createElement('span'), icon = doc.createElement('i'), word = doc.createElement('span');
        badge.className = 'patches-badge patches-badge--restart patches-icon-badge patches-restart-badge';
        badge.setAttribute('title', text);
        badge.setAttribute('aria-label', text);
        icon.className = 'ri-arrow-go-back-line';
        icon.setAttribute('aria-hidden', 'true');
        word.className = 'patches-icon-badge__text';
        word.textContent = text;
        badge.appendChild(icon);
        badge.appendChild(word);

        return badge;
    }

    function apply($, id, status, cfg) {
        var v = view(status, cfg);
        $('.patches-status-label[data-segment-id="' + id + '"]').each(function () {
            $(this).removeClass(STATES).addClass(v.labelClass).attr('data-status', status);
            fillLabel(this, v);
        });
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
        addStatusColumns(root.document, cfg);
        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; place(root.document); colorCounts(root.document); addStatusColumns(root.document, cfg); });
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
        $(label).removeClass(STATES).addClass(v.labelClass).attr('data-status', status);
        fillLabel(label, v);
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
     * holds the links (the row, or the document); `path` is the route of the item (`segment-rebuild`, `campaign-rebuild`). The href must END
     * with the id: a "contains" match would also take the items of ids 10 to 19 when the id is 1.
     */
    function toggleUpdate(scope, path, id, status) {
        var links = scope.querySelectorAll('a[href$="/s/' + path + '/' + id + '"]');
        for (var k = 0; k < links.length; k++) {
            if (status === 'published') { links[k].removeAttribute('hidden'); } else { links[k].setAttribute('hidden', 'hidden'); }
        }
    }

    /** The lists that have a Status column: the CSS selector of the table and the kind (the name in the class of the column). */
    var COLUMNS = [
        {table: '#leadListTable', kind: 'segment'},
        {table: '#campaignTable', kind: 'campaign'},
        {table: '#userTable', kind: 'user'},
        {table: 'table.email-list', kind: 'email'}
    ];

    /**
     * The status of a list is a column of its own, right after the menu column (before "Prefixo", the avatar and "Nome"): a header
     * `th.col-patches-status` and, in every row, a cell that holds the label (core, or this script, prints it with the name). The
     * header is plain, not a sort link. The label becomes an icon with the word on hover (fillLabel). Done once per drawing of the table (a
     * sort, a page or a search draws it again); returns whether it added the column. It is placed by position (second column), so it
     * does not depend on the order in which the Prefixo column is added. `cfg`: the texts (column, active, inactive, restart).
     */
    function addStatusColumn(doc, spec, cfg) {
        var table = doc.querySelector(spec.table);
        // not before a label exists: the user and email lists get theirs from the script (syncNamedRows), which finds the name cell by its position
        if (!table || table.querySelector('th.col-patches-status') || !table.querySelector('.patches-status-label')) { return false; }
        var head = table.querySelector('thead tr'), first = head && head.querySelector('th');
        if (!first) { return false; }
        var columnClass = 'col-patches-status col-' + spec.kind + '-status';

        var th = doc.createElement('th'), box = doc.createElement('span'), text = doc.createElement('span');
        th.className = columnClass + ' pa-0';
        th.setAttribute('scope', 'col');
        box.className = 'btn btn-ghost btn-block jc-space-between';
        box.style.pointerEvents = 'none';
        box.style.color = 'inherit'; // a ghost button takes the link color; the other headers are black
        text.className = 'fw-b';
        text.textContent = cfg.column;
        box.appendChild(text);
        th.appendChild(box);
        first.parentNode.insertBefore(th, first.nextSibling);

        var rows = table.querySelectorAll('tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var cell = rows[k].querySelector('td');
            if (!cell) { continue; }
            var td = doc.createElement('td'), label = rows[k].querySelector('.patches-status-label');
            td.className = columnClass;
            cell.parentNode.insertBefore(td, cell.nextSibling);
            if (!label) { continue; }
            label.setAttribute('data-placed', '1');
            td.appendChild(label);
            var status = label.getAttribute('data-status');
            // a label that is spinning (core is working) is painted by paint() when the work ends
            if (status === 'published' || status === 'unpublished') { fillLabel(label, view(status, cfg)); }
            if (label.getAttribute('data-restart') === '1') { td.appendChild(restartBadge(doc, cfg.restart)); }
        }

        return true;
    }

    /** The Status column of every list that is on the page and has none yet. */
    function addStatusColumns(doc, cfg) {
        for (var k = 0; k < COLUMNS.length; k++) { addStatusColumn(doc, COLUMNS[k], cfg); }
    }

    /** Brings the label and the menu item of every campaign row to the state of its core icon. */
    function syncCampaigns($, cfg) {
        if (!root.document.querySelector('.patches-status-label[data-campaign-id]')) { return; } // not a campaign list: no row to look at
        addStatusColumns(root.document, cfg);
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
            var row = rows[k], icon = iconOf(row, kind);
            var label = row.querySelector('.patches-status-label');
            if (!label) {
                // only a row that has no label yet needs its name cell, found by position: once the Status column exists the positions have moved
                var name = row.querySelector(nameSelector);
                if (!name) { continue; }
                label = root.document.createElement('span');
                label.className = 'patches-badge patches-status-label';
                label.setAttribute('data-placed', '1');
                label.setAttribute('data-status', '');
                name.insertBefore(label, name.firstChild);
            }
            paint($, label, icon, icon && icon.getAttribute('data-status') === 'unpublished' ? 'unpublished' : 'published',
                $(row).find('a[' + attr + ']'), attr, cfg);
        }
        addStatusColumns(root.document, cfg);
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

    var api = {addStatusColumn: addStatusColumn, addStatusColumns: addStatusColumns, COLUMNS: COLUMNS, install: install, installCampaign: installCampaign, toggleUpdate: toggleUpdate, installUser: installUser, installEmail: installEmail, statusOf: statusOf, view: view, place: place, colorCounts: colorCounts, withoutAlias: withoutAlias};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesListStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
