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
     * An informative icon badge beside the status in the Status column: an icon that opens on hover to show the word (also its title and
     * aria-label), blue (`flag`, `restart`) or red (`failed`). `o`: icon, text, modifier, and optionally `extra` (more classes) and
     * `wide` (a long word, such as the warning of core).
     */
    function extraBadge(doc, o) {
        var badge = doc.createElement('span'), icon = doc.createElement('i'), word = doc.createElement('span');
        badge.className = 'patches-badge patches-badge--' + o.modifier + ' patches-icon-badge patches-extra-badge' + (o.wide ? ' patches-extra-badge--wide' : '') + (o.extra ? ' ' + o.extra : '');
        badge.setAttribute('title', o.text);
        badge.setAttribute('aria-label', o.text);
        icon.className = o.icon;
        icon.setAttribute('aria-hidden', 'true');
        word.className = 'patches-icon-badge__text';
        word.textContent = o.text;
        badge.appendChild(icon);
        badge.appendChild(word);

        return badge;
    }

    /** The badge next to the status of a campaign that lets its contacts enter again (allowRestart). Informative only, it is not touched after the table is drawn. */
    function restartBadge(doc, text) {
        return extraBadge(doc, {icon: 'ri-arrow-go-back-line', text: text, modifier: 'restart', extra: 'patches-restart-badge'});
    }

    /**
     * The icons core prints next to the name in the segment list (LeadBundle List/_list_row.html.twig), in the order they are shown as badges after the status (`icon`: the one of the badge; core 7 prints the rebuild warning as `ri-error-warning-line` inside a label, on 5 it is `ri-error-warning-line-circle`, a class with no glyph in the icon font):
     * global, has filters, and the two warnings (not rebuilt for long, slow to build, red). `holder`: the label that holds a warning's icon and its tooltip,
     * whose number (hours, seconds) goes into the short word of cfg (`word`, with %count%); the sentence of core is the fallback when it has no number.
     */
    var SEGMENT_FLAGS = [
        {key: 'global', icon: 'ri-global-line', selector: 'i.ri-group-2-fill', modifier: 'flag', word: 'global'},
        {key: 'filters', icon: 'ri-filter-2-fill', selector: 'i.ri-filter-2-fill', modifier: 'flag', word: 'filters'},
        {key: 'rebuild', icon: 'ri-error-warning-line', selector: 'label > i.ri-error-warning-line', modifier: 'failed', holder: true, word: 'rebuild'},
        {key: 'slow', icon: 'ri-time-line', selector: 'i.ri-time-line', modifier: 'failed', holder: true, word: 'slow'}
    ];

    /** Moves the icons of a segment row out of the name cell into badges at the end of the box of its Status cell. */
    function moveSegmentFlags(doc, row, box, cfg) {
        for (var k = 0; k < SEGMENT_FLAGS.length; k++) {
            var f = SEGMENT_FLAGS[k], icon = row.querySelector(f.selector);
            if (!icon) { continue; }
            var holder = f.holder ? icon.parentNode : icon;
            var text = cfg[f.word];
            if (f.holder) {
                var sentence = holder.getAttribute('data-original-title') || '', count = /\d+(?:[.,]\d+)?/.exec(sentence);
                text = count ? text.replace('%count%', String(Math.round(parseFloat(count[0].replace(',', '.'))))) : sentence; // core prints 72.0219 hours
            }
            box.appendChild(extraBadge(doc, {icon: f.icon, text: text || '', modifier: f.modifier, wide: !!f.holder}));
            holder.parentNode.removeChild(holder);
        }
    }

    /**
     * The icons core prints next to the name in the email list (EmailBundle Email/_list.html.twig), shown as badges after the status. The first one
     * is the TYPE of the email, always there: "Broadcast" (core's pie chart, an email of a segment) or "Campanha" (no icon in core, the email of campaigns,
     * forms and points): the same color, so the color says the type. Then, each with its own color, A/B test, translation and draft. `holder`: the
     * span of core with the tooltip around the icon, removed with it.
     */
    var EMAIL_TYPES = {
        broadcast: {icon: 'ri-pie-chart-fill', word: 'emailBroadcast'},
        campaign: {icon: 'ri-mail-send-fill', word: 'emailCampaign'}
    };
    var EMAIL_FLAGS = [
        {key: 'abtest', icon: 'ri-test-tube-fill', selector: 'span[data-toggle="tooltip"] > i.ri-organization-chart', modifier: 'abtest', word: 'emailAbtest'},
        {key: 'translation', icon: 'ri-translate-2', selector: 'span[data-toggle="tooltip"] > i.ri-translate', modifier: 'translation', word: 'emailTranslation'},
        {key: 'draft', icon: 'ri-draft-line', selector: 'span[data-toggle="tooltip"] > i.fa-file', modifier: 'draft', word: 'emailDraft'}
    ];
    var EMAIL_BROADCAST_ICON = 'span[data-toggle="tooltip"] > i.ri-pie-chart-line';

    /** Moves the icons of an email row out of the name cell into badges at the end of the box of its Status cell: the type first. */
    function moveEmailFlags(doc, row, box, cfg) {
        var pie = row.querySelector(EMAIL_BROADCAST_ICON), type = EMAIL_TYPES[pie ? 'broadcast' : 'campaign'];
        box.appendChild(extraBadge(doc, {icon: type.icon, text: cfg[type.word], modifier: 'emailtype'}));
        if (pie) { pie.parentNode.parentNode.removeChild(pie.parentNode); }
        for (var k = 0; k < EMAIL_FLAGS.length; k++) {
            var f = EMAIL_FLAGS[k], icon = row.querySelector(f.selector);
            if (!icon) { continue; }
            box.appendChild(extraBadge(doc, {icon: f.icon, text: cfg[f.word], modifier: f.modifier}));
            icon.parentNode.parentNode.removeChild(icon.parentNode);
        }
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
     * The contact count of a segment row (`.col-count`: a `<span>` with the link inside on Mautic 7, the `a.col-count` itself on Mautic 5; "Ver 6 Contatos") is a patches-badge like the status: translucent blue
     * (--info) when the segment has contacts, translucent gray (--neutral) when it is empty. Core prints it as `label label-gray`
     * with size="sm" and fills the text by ajax after the page loads, so this runs on every change.
     */
    function colorCounts(doc) {
        var badges = doc.querySelectorAll('.col-count');
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
        // every ajax page change runs the injected script again: a style already in the page is not added twice
        root.mauticPatchesStyles = root.mauticPatchesStyles || {};
        if (!root.mauticPatchesStyles[cfg.style]) {
            root.mauticPatchesStyles[cfg.style] = true;
            var style = root.document.createElement('style');
            style.textContent = cfg.style;
            root.document.head.appendChild(style);
        }

        if (root.mauticPatchesStatusLabels) { return; }
        root.mauticPatchesStatusLabels = true;

        // the labels of a list that is in the page, and of every list loaded later
        var draw = function () { place(root.document); colorCounts(root.document); addStatusColumns(root.document, cfg); addContactStatusColumn(root.document, cfg); };
        draw();
        root.document.addEventListener('DOMContentLoaded', draw);
        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; draw(); });
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

        root.document.addEventListener('DOMContentLoaded', function () { sync($, cfg); }); // the page is whole: the rows the draws before it could not see
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

    /** The plain "Status" header (not a sort link) that goes right after the first header of `head`. */
    function statusHeader(doc, first, columnClass, title) {
        var th = doc.createElement('th'), box = doc.createElement('span'), text = doc.createElement('span');
        th.className = columnClass + ' pa-0';
        th.setAttribute('scope', 'col');
        box.className = 'btn btn-ghost btn-block jc-space-between';
        box.style.pointerEvents = 'none';
        box.style.color = 'inherit'; // a ghost button takes the link color; the other headers are black
        text.className = 'fw-b';
        text.textContent = title;
        box.appendChild(text);
        th.appendChild(box);
        first.parentNode.insertBefore(th, first.nextSibling);
    }

    /**
     * The status of a list is a column of its own, right after the menu column (before "Prefixo", the avatar and "Nome"): a header
     * `th.col-patches-status` and, in every row, a cell that holds the label (core, or this script, prints it with the name). The
     * header is plain, not a sort link. The label becomes an icon with the word on hover (fillLabel). Done once per drawing of the table (a
     * sort, a page or a search draws it again); returns whether it added the column. It is placed by position (second column), so it
     * does not depend on the order in which the Prefixo column is added. `cfg`: the texts (column, active, inactive, restart).
     */
    function addStatusColumn(doc, spec, cfg) {
        // not while the page is still being parsed: a table that is half there would get its column once, with rows missing (the sync runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        var table = doc.querySelector(spec.table);
        // not before a label exists: the user and email lists get theirs from the script (syncNamedRows), which finds the name cell by its position
        if (!table || table.querySelector('th.col-patches-status') || !table.querySelector('.patches-status-label')) { return false; }
        var head = table.querySelector('thead tr'), first = head && head.querySelector('th');
        if (!first) { return false; }
        var columnClass = 'col-patches-status col-' + spec.kind + '-status';

        statusHeader(doc, first, columnClass, cfg.column);

        var rows = table.querySelectorAll('tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var cell = rows[k].querySelector('td');
            if (!cell) { continue; }
            var td = doc.createElement('td'), label = rows[k].querySelector('.patches-status-label');
            td.className = columnClass;
            cell.parentNode.insertBefore(td, cell.nextSibling);
            if (!label) { continue; }
            // the badges sit side by side in a box in the flow of the cell: one that opens on hover widens the column and the browser lays out the table
            var box = doc.createElement('div');
            box.className = 'patches-status-box';
            td.appendChild(box);
            label.setAttribute('data-placed', '1');
            box.appendChild(label);
            var status = label.getAttribute('data-status');
            // a label that is spinning (core is working) is painted by paint() when the work ends
            if (status === 'published' || status === 'unpublished') { fillLabel(label, view(status, cfg)); }
            if (label.getAttribute('data-restart') === '1') { box.appendChild(restartBadge(doc, cfg.restart)); }
            if (spec.kind === 'segment') { moveSegmentFlags(doc, rows[k], box, cfg); }
            if (spec.kind === 'email') { moveEmailFlags(doc, rows[k], box, cfg); }
        }

        return true;
    }

    /**
     * The channels of the contact list: the badge of each shows whether the contact may receive on it. `dnc` is the Do Not Contact channel of core
     * the badge follows: HSM has no channel of its own (the n8n plugin checks `sms` for SMS and HSM), so its badge follows `sms`.
     */
    var CONTACT_CHANNELS = [
        {key: 'email', dnc: 'email', icon: 'ri-mail-line', word: 'contactEmail'},
        {key: 'sms', dnc: 'sms', icon: 'ri-message-2-line', word: 'contactSms'},
        {key: 'hsm', dnc: 'sms', icon: 'ri-whatsapp-line', word: 'contactHsm'}
    ];

    /**
     * The icon core 7 prints after the prohibited one in the red Do Not Contact label beside the name (LeadBundle dnc_small.html.twig): a mail for
     * email and a chat bubble for EVERY other channel, which this list calls sms (Mautic 5 prints an `ico-<channel>` class instead).
     */
    var DNC_ICONS = [{cls: 'ri-mail-line', channel: 'email'}, {cls: 'ri-chat-1-line', channel: 'sms'}];

    /** The Do Not Contact channels of a row, read from the red labels core prints beside the name. */
    function blockedChannels(row) {
        var labels = row.querySelectorAll('td:not(.col-patches-status) .label-danger'), blocked = {};
        for (var k = 0; k < labels.length; k++) {
            for (var j = 0; j < DNC_ICONS.length; j++) {
                if (labels[k].querySelector('i.' + DNC_ICONS[j].cls)) { blocked[DNC_ICONS[j].channel] = true; }
            }
        }

        return blocked;
    }

    /** The badges of a contact: [{channel, icon, text, modifier}], text is the name of the channel; red when the channel is blocked, green when it is not. */
    function contactBadges(blocked, cfg) {
        return CONTACT_CHANNELS.map(function (c) {
            var isBlocked = blocked[c.dnc] === true;

            return {
                channel: c.key,
                icon: c.icon,
                text: cfg[c.word], // only the name of the channel: the color tells the state
                modifier: isBlocked ? 'failed' : 'production'
            };
        });
    }

    /** Whether a path is the contact list itself (Mautic may be served under a subdirectory or index.php): /s/contacts and its pages, not a contact's page. */
    function isContactListPath(path) {
        return /\/s\/contacts(?:\/\d+)?$/.test(path);
    }

    /**
     * The Status column of the contact list (only the list at /s/contacts, not the contact tables of a segment or campaign): one icon badge per
     * channel (email, SMS, HSM), green when the contact can receive and red when it is on the Do Not Contact list of the channel. Built from the red
     * labels core prints beside the name (hidden by CSS). Once per drawing of the table; returns whether it added the column.
     */
    function addContactStatusColumn(doc, cfg) {
        // not while the page is still being parsed: a table that is half there would get its column once, with rows missing (the setup runs again at DOMContentLoaded)
        if (doc.readyState === 'loading') { return false; }
        var table = doc.querySelector('#leadTable');
        if (!table || !isContactListPath(root.location.pathname) || table.querySelector('th.col-patches-status')) { return false; }
        var head = table.querySelector('thead tr'), first = head && head.querySelector('th');
        if (!first) { return false; }
        statusHeader(doc, first, 'col-patches-status col-contact-status', cfg.column);

        var rows = table.querySelectorAll('tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var cell = rows[k].querySelector('td');
            if (!cell) { continue; }
            var td = doc.createElement('td'), box = doc.createElement('div'), badges = contactBadges(blockedChannels(rows[k]), cfg);
            td.className = 'col-patches-status col-contact-status';
            box.className = 'patches-status-box';
            for (var j = 0; j < badges.length; j++) { box.appendChild(extraBadge(doc, badges[j])); }
            td.appendChild(box);
            cell.parentNode.insertBefore(td, cell.nextSibling);
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

    function installContact($, Mautic, cfg) {
        if (root.mauticPatchesContactStatus) { return; }
        root.mauticPatchesContactStatus = true;
        setup(cfg);
    }

    var api = {EMAIL_FLAGS: EMAIL_FLAGS, EMAIL_TYPES: EMAIL_TYPES, addContactStatusColumn: addContactStatusColumn, isContactListPath: isContactListPath, blockedChannels: blockedChannels, contactBadges: contactBadges, CONTACT_CHANNELS: CONTACT_CHANNELS, installContact: installContact, addStatusColumn: addStatusColumn, addStatusColumns: addStatusColumns, COLUMNS: COLUMNS, SEGMENT_FLAGS: SEGMENT_FLAGS, install: install, installCampaign: installCampaign, toggleUpdate: toggleUpdate, installUser: installUser, installEmail: installEmail, statusOf: statusOf, view: view, place: place, colorCounts: colorCounts, withoutAlias: withoutAlias};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesListStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
