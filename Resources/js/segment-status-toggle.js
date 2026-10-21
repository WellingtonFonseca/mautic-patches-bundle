/*
 * Activate / deactivate a segment from the three-dots menu of the segment list. The status is shown by a label
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

    /** Moves the labels that are not at the start of their block yet; returns how many it moved. */
    function place(doc) {
        var moved = 0;
        var labels = doc.querySelectorAll('.segment-status-label:not([data-placed])');
        for (var k = 0; k < labels.length; k++) {
            var label = labels[k], parent = label.parentNode;
            if (parent && parent.firstChild !== label) { parent.insertBefore(label, parent.firstChild); moved++; }
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

    function install($, Mautic, cfg) {
        if (!$ || root.mauticPatchesSegmentStatus) { return; }
        root.mauticPatchesSegmentStatus = true;

        var style = root.document.createElement('style');
        style.textContent = cfg.style;
        root.document.head.appendChild(style);

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

    var api = {install: install, statusOf: statusOf, view: view, place: place, measure: measure};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesSegmentStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
