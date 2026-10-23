/*
 * Activate / deactivate a segment (and, in installCampaign and installUser, a campaign and a user) from the three-dots menu of the segment list. The status is shown by a label
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

    /** The core icon of a campaign row (hidden), whose state tells the status; null when the row has none. */
    function coreIcon(row) { return row.querySelector('i[class*="campaign-publish-icon"]'); }

    /** Brings the label and the menu item of every campaign row to the state of its core icon: spinning, or published / not. */
    function syncCampaigns($, cfg) {
        var rows = root.document.querySelectorAll('tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], label = row.querySelector('.segment-status-label[data-campaign-id]');
            var icon = label && coreIcon(row);
            if (!icon) { continue; }
            var id = label.getAttribute('data-campaign-id');
            if (/\bri-spin\b/.test(icon.className)) {
                $(label).removeClass('label-success label-danger').addClass('label-gray')
                    .html('<i class="ri-loader-3-line ri-spin" aria-hidden="true"></i>').attr('data-status', 'busy');
                continue;
            }
            var status = icon.getAttribute('data-status') === 'published' ? 'published' : 'unpublished';
            if (label.getAttribute('data-status') === status) { continue; }
            var v = view(status, cfg);
            $(label).removeClass('label-success label-danger label-gray').addClass(v.labelClass).attr('data-status', status).text(v.labelText);
            $('a[data-campaign-status-action][data-campaign-id="' + id + '"]').each(function () {
                var $link = $(this);
                $link.attr('data-campaign-status-action', v.action).attr('aria-label', v.actionText);
                $link.find('span').first().text(v.actionText);
                $link.find('i').first().attr('class', v.actionIcon);
            });
        }
    }

    function installCampaign($, Mautic, cfg) {
        if (!$ || root.mauticPatchesCampaignStatus) { return; }
        root.mauticPatchesCampaignStatus = true;
        setup(cfg);

        // the menu item clicks core's icon of the row (hidden): its own onclick asks for the confirmation when deactivating
        $(root.document).on('click', 'a[data-campaign-status-action]', function (event) {
            event.preventDefault();
            var row = this.closest('tr'), icon = row && coreIcon(row);
            if (icon) { icon.click(); }
        });

        // core changes the icon (the spin while the request runs, the new icon when it is answered): the label follows
        var pending = false;
        new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; syncCampaigns($, cfg); });
        }).observe(root.document.body || root.document.documentElement, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-status']});
    }

    /** The core icon of a user row (hidden), whose state tells the status; null on the row of the logged user (core prints none). */
    function userIcon(row) { return row.querySelector('i[class*="user-publish-icon"]'); }

    /**
     * Brings the label and the menu item of every user row to the state of its core icon (spinning, or published / not). The user list has
     * no hook for the label: it is made here, at the start of the name. The row without an icon is the logged user, always active.
     */
    function syncUsers($, cfg) {
        var rows = root.document.querySelectorAll('#userTable tbody tr');
        for (var k = 0; k < rows.length; k++) {
            var row = rows[k], icon = userIcon(row), name = row.querySelector('td:nth-child(3) > div');
            if (!name) { continue; }
            var label = row.querySelector('.segment-status-label');
            var status = icon ? (icon.getAttribute('data-status') === 'published' ? 'published' : 'unpublished') : 'published';
            if (!label) {
                label = root.document.createElement('span');
                label.className = 'label segment-status-label';
                label.setAttribute('data-placed', '1');
                label.setAttribute('data-status', '');
                name.insertBefore(label, name.firstChild);
            }
            if (icon && /\bri-spin\b/.test(icon.className)) {
                $(label).removeClass('label-success label-danger').addClass('label-gray')
                    .html('<i class="ri-loader-3-line ri-spin" aria-hidden="true"></i>').attr('data-status', 'busy');
                continue;
            }
            if (label.getAttribute('data-status') === status) { continue; }
            var v = view(status, cfg);
            $(label).removeClass('label-success label-danger label-gray').addClass(v.labelClass).attr('data-status', status).text(v.labelText);
            $(row).find('a[data-user-status-action]').each(function () {
                var $link = $(this);
                $link.attr('data-user-status-action', v.action).attr('aria-label', v.actionText);
                $link.find('span').first().text(v.actionText);
                $link.find('i').first().attr('class', v.actionIcon);
            });
        }
    }

    function installUser($, Mautic, cfg) {
        if (!$ || root.mauticPatchesUserStatus) { return; }
        root.mauticPatchesUserStatus = true;
        setup(cfg);

        // the menu item clicks core's icon of the row (hidden): core sends the request and checks the permission
        $(root.document).on('click', 'a[data-user-status-action]', function (event) {
            event.preventDefault();
            var row = this.closest('tr'), icon = row && userIcon(row);
            if (icon) { icon.click(); }
        });

        // core changes the icon (the spin while the request runs, the new icon when it is answered): the label follows
        var pending = false;
        var observer = new root.MutationObserver(function () {
            if (pending) { return; }
            pending = true;
            root.requestAnimationFrame(function () { pending = false; syncUsers($, cfg); });
        });
        observer.observe(root.document.body || root.document.documentElement, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-status']});
        syncUsers($, cfg);
    }

    var api = {install: install, installCampaign: installCampaign, installUser: installUser, statusOf: statusOf, view: view, place: place, measure: measure, withoutAlias: withoutAlias};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
