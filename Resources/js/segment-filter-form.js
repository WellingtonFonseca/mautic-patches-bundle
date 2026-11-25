/*
 * The data layer of the segment filters screen (segment-filter-ui.js draws it). Mautic's own filter area (#leadlist_filters,
 * its panels and their hidden inputs) stays in the DOM, hidden, and it is the ONLY place the filters live: core posts it,
 * validates it and saves it. Nothing here keeps a state of its own; every change is made by calling core's functions
 * (Mautic.addLeadListFilter, Mautic.reorderSegmentFilters, the click of core's copy / remove buttons, the change event of the
 * glue and operator selects) so the POST is the one core's own screen would send.
 *
 * The value cell of a row is core's `.col-sm-6` of the filter (its `.properties-form`, with chosen, pickers, chips) MOVED
 * out of the panel, not cloned: ids, names and widgets keep working and it stays inside the <form>. TRAP: reorderSegmentFilters
 * renames the inputs inside each panel only, so before anything that renumbers or clones panels the moved nodes go back into
 * their panels (dockAll) and the rows are drawn again afterwards (the `changed` hook); Mautic.reorderSegmentFilters is wrapped
 * once for that, so a reorder started by core (sortable, remove) is safe too. A row is linked to its panel by element, never by number.
 *
 * Writes are serialized (queue): an ajax answer (the value form after an operator change) never lands on a renumbered filter.
 */
(function (root) {
    'use strict';

    var LIST = '#leadlist_filters';
    var DRAFT_BASE = 100000;

    var ctx = {$: null, Mautic: null, changed: function () {}};
    // value-form reloads in flight, by filter number; `settled` listeners run when one is answered
    var inflight = {};
    var settled = [];
    var REPLY_TIMEOUT = 15000;
    var queue = null;
    // >0 while core runs a multi-step operation (copy of a group): the redraw waits for its end
    var quiet = 0;

    /** The editable segment form (not a Dynamic Content / DWC filter, which shares lead.js and must not be touched). */
    function active($) {
        $ = $ || ctx.$;
        return !!($ && $(LIST).length && $(LIST).closest('form[name="leadlist"]').length && !$('.dynamic-content-filter, .dwc-filter').length);
    }

    function panels($) {
        $ = $ || ctx.$;
        return $(LIST).children('.segment-filter').not('[data-patches-removing]');
    }

    function body($panel) { return $panel.children('.panel-body').first(); }

    /** The value column of a filter: in its panel, or in the row it was moved to (remembered on the panel). */
    function valueOf($panel) {
        var $moved = $panel.data('patchesValue');
        if ($moved && $moved.length) { return $moved; }
        var $col = body($panel).children('.col-sm-6').first();
        $panel.data('patchesValue', $col);

        return $col;
    }

    function glueOf($panel) { return $panel.find('select.glue-select').first(); }
    function operatorOf($panel) { return body($panel).find('select[id$="_operator"]').first(); }
    function hidden($panel, name) { return body($panel).children('input[id$="_' + name + '"]').first(); }

    /** A filter read from the form (the row is drawn from this, never from a state of ours). */
    function readRow($panel) {
        var $name = body($panel).children('.field-name').first();
        var $copy = $panel.find('.segment-button .copy-filter-group').first();
        var $operator = operatorOf($panel);

        return {
            num: Number(($panel.attr('id') || '').replace(/^.*_/, '')),
            glue: glueOf($panel).val() || 'and',
            first: $panel.prevAll('.segment-filter').length === 0,
            inGroup: $panel.hasClass('in-group'),
            label: ($name.attr('data-patches-label') || $name.text() || '').replace(/\s+/g, ' ').trim(),
            object: hidden($panel, 'object').val() || '',
            field: hidden($panel, 'field').val() || '',
            operator: $operator.val(),
            operators: $operator.find('option').map(function () { return {value: this.value, text: this.text}; }).get(),
            glues: glueOf($panel).find('option').map(function () { return {value: this.value, text: this.text}; }).get(),
            canCopy: !!$copy.length && !$copy.hasClass('hide')
        };
    }

    /** Every moved value column goes back into its panel (before core renumbers or clones the panels). */
    function dockAll() {
        panels().each(function () {
            var $panel = ctx.$(this);
            var $col = $panel.data('patchesValue');
            if ($col && $col.length && !$col.parent().is(body($panel))) { body($panel).append($col); }
        });
    }

    // ---- queue -------------------------------------------------------------------------------------------------

    function pendingTotal() {
        var total = 0;
        Object.keys(inflight).forEach(function (key) { total += inflight[key]; });

        return total;
    }

    function whenIdle() {
        return new Promise(function (resolve) {
            if (!pendingTotal()) { resolve(); return; }
            var done = function () { if (!pendingTotal()) { settled.splice(settled.indexOf(done), 1); resolve(); } };
            settled.push(done);
        });
    }

    function notifySettled() { settled.slice().forEach(function (fn) { fn(); }); }

    /** Runs a write after the ones before it, then waits for the value form it may have asked for. */
    function enqueue(job) {
        queue = (queue || Promise.resolve()).then(function () {
            try { job(); } catch (e) { if (root.console) { root.console.error(e); } }

            return whenIdle();
        }).then(function () { ctx.changed(); });

        return queue;
    }

    // ---- writes (each one calls core) --------------------------------------------------------------------------

    function setGlue($panel, value) {
        return enqueue(function () {
            var $glue = glueOf($panel);
            $glue.val(value).trigger('change');
        });
    }

    function setOperator($panel, value) {
        return enqueue(function () { operatorOf($panel).val(value).trigger('change'); });
    }

    /** The copy of a filter, ready to be put after it (value column docked in it). */
    function copyOf($, $origin) {
        $origin.find('.properties-form .choice-wrapper select').each(function () {
            try { $(this).chosen('destroy'); } catch (e) { /* not a chosen select */ }
        });
        $origin.find('select option').each(function () {
            var $option = $(this);
            $option.attr('selected', $option.is(':selected') ? 'selected' : null);
        });
        var $clone = $origin.clone(false);
        // the chips of the value are a widget on top of the input: the input stays, the widget is drawn again
        $clone.find('.segment-in-chips').closest('.input-group').remove();
        $clone.find('input[data-in-chips]').removeAttr('data-in-chips').show();
        // the first filter has its glue hidden; a copy below it needs it (it is AND, so it stays in the group)
        $clone.find('.panel-glue').removeClass('hide');

        return $clone;
    }

    function duplicate($panel) {
        return enqueue(function () {
            var $ = ctx.$;
            var Mautic = ctx.Mautic;
            dockAll();
            var $clone = copyOf($, $panel);
            $panel.after($clone);
            Mautic.reorderSegmentFilters();
            Mautic.triggerOnPropertiesFormLoadedEvent('#' + $clone.attr('id'));
            Mautic.segmentFilter().attachEvents($clone);
            ctx.changed();
            if (typeof root.mauticPatchesInChipsScan === 'function') { root.mauticPatchesInChipsScan(); }
            valueOf($clone).find('input:visible, select').first().trigger('focus');
        });
    }

    /** Core's own group copy (the whole OR group to the end of the list): its button is clicked, so core's handler runs. */
    function copyGroup($panel) {
        return enqueue(function () {
            dockAll();
            quiet++;
            try { $panel.find('.segment-button .copy-filter-group').first().trigger('click'); } finally { quiet--; }
            ctx.changed();
        });
    }

    function remove($panel) {
        // out of the list at once: core removes the panel after its fade, and that is when it renumbers
        // its value column goes home first: the row is dropped now, the panel (and its inputs) stays in the form until core removes it
        var $col = $panel.data('patchesValue');
        if ($col && $col.length && !$col.parent().is(body($panel))) { body($panel).append($col); }
        $panel.attr('data-patches-removing', '1');
        // a Save before core's removal must not post the filter (disabled inputs are not submitted)
        $panel.find(':input').prop('disabled', true);
        ctx.changed();

        // the click waits for the replies in flight: they must not land on a renumbered panel
        return enqueue(function () {
            $panel.find('.segment-button .remove-selected').first().trigger('click');
        });
    }

    /** Puts the panels in the order given (elements) and lets core renumber. */
    function order(panelElements) {
        return enqueue(function () {
            var $ = ctx.$;
            dockAll();
            var $list = $(LIST);
            panelElements.forEach(function (el) { $list.append(el); });
            ctx.Mautic.reorderSegmentFilters();
        });
    }

    // ---- install -----------------------------------------------------------------------------------------------

    function install($, Mautic, changed) {
        ctx.$ = $;
        ctx.Mautic = Mautic;
        ctx.changed = changed || ctx.changed;
        if (root.mauticPatchesSegmentFilterFormOn) { return; }
        root.mauticPatchesSegmentFilterFormOn = true;

        // a reorder (core's sortable, a removal, a copy) renames the inputs inside the panels: the value columns go home first, the rows are drawn after
        var reorder = Mautic.reorderSegmentFilters;
        if (!reorder.patchesDocked) {
            Mautic.reorderSegmentFilters = function () {
                if (!active($)) { return reorder.apply(this, arguments); }
                dockAll();
                try { return reorder.apply(this, arguments); } finally { if (!quiet) { ctx.changed(); } }
            };
            Mautic.reorderSegmentFilters.patchesDocked = true;
        }

        // the value form of a filter is loaded by ajax after an operator change: count the requests in flight, by filter
        var loadFilterForm = Mautic.loadFilterForm;
        Mautic.loadFilterForm = function (filterNum, fieldObject, fieldAlias, operator, resultHtml) {
            var args = Array.prototype.slice.call(arguments);
            if (typeof resultHtml === 'function') {
                inflight[filterNum] = (inflight[filterNum] || 0) + 1;
                var counted = true;
                var release = function () {
                    if (!counted) { return false; }
                    counted = false;
                    inflight[filterNum] = Math.max(0, (inflight[filterNum] || 1) - 1);

                    return true;
                };
                // a reply core cannot handle (no viewParameters) never reaches resultHtml: ajaxComplete / this timer settle it
                var timer = setTimeout(function () { if (release()) { notifySettled(); } }, REPLY_TIMEOUT);
                args[4] = function () {
                    clearTimeout(timer);
                    release();
                    var out;
                    try { out = resultHtml.apply(this, arguments); } finally { notifySettled(); }

                    return out;
                };
            }

            return loadFilterForm.apply(this, args);
        };
        $(document).ajaxError(function () { inflight = {}; notifySettled(); });
        // no request left in the page, yet a count is open: its reply was dropped by core, so the queue must not wait for it
        $(document).ajaxComplete(function () {
            setTimeout(function () {
                if (!$.active && Object.keys(inflight).some(function (k) { return inflight[k] > 0; })) { inflight = {}; notifySettled(); }
            }, 0);
        });
    }

    /** True while a value form of a draft (the add bar) is being loaded. */
    function draftPending() {
        return Object.keys(inflight).some(function (key) { return Number(key) >= DRAFT_BASE && inflight[key] > 0; });
    }

    function onSettled(fn) { settled.push(function () { fn(); }); }

    var api = {
        install: install, active: active, panels: panels, readRow: readRow, valueOf: valueOf, glueOf: glueOf, operatorOf: operatorOf,
        dockAll: dockAll, copyOf: copyOf, setGlue: setGlue, setOperator: setOperator, duplicate: duplicate, copyGroup: copyGroup,
        remove: remove, order: order, draftPending: draftPending, onSettled: onSettled, DRAFT_BASE: DRAFT_BASE, LIST: LIST
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.MauticPatchesSegmentFilterForm = api;
    }
})(typeof window !== 'undefined' ? window : this);
