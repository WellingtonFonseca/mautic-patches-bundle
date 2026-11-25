/*
 * The segment filters screen drawn by us (Filtros tab of the segment form): one row per filter (E / OU, field with its icon,
 * condition, value, badges Clone / Duplicar / Remover) and a floating bar at the bottom to add a filter. Mautic's own filter area
 * stays in the DOM, hidden, as the data layer (segment-filter-form.js): the row shows what the hidden form holds and every
 * change goes back to it through that adapter, so core keeps saving, validating and rebuilding.
 *
 * Fail-open: core's area is hidden only after the rows were drawn (class patches-sf-on on #filters); if a draw throws, the
 * class is taken off and core's own screen is shown again.
 */
(function (root) {
    'use strict';

    var DOCK = 'patches-filter-dock';
    var LABEL = 'Duplicar';
    var OWNERS = {lead: 'Contato', company: 'Empresa'};
    // room kept for the floating alert (about two lines, 60px) and its 12px gap: fixed, whether an alert is showing or not
    var ALERT_SPACE = 72;
    var Form = null;

    var BADGES = [
        {key: 'copy', icon: 'ri-add-line', css: 'patches-badge--test', label: 'Clone'},
        {key: 'duplicate', icon: 'ri-add-line', css: 'patches-badge--flag', label: LABEL},
        {key: 'remove', icon: 'ri-subtract-line', css: 'patches-badge--failed', label: 'Remover'}
    ];

    function blockTyping(e) {
        if (e.type === 'keydown') {
            if (!(!(e.ctrlKey || e.metaKey) && e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Enter')) { return; }
        }
        e.preventDefault();
    }

    // ---- rows ----------------------------------------------------------------------------------------------------

    var rowOf = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

    function fieldCell($, data) {
        var group = OWNERS[data.object] || '';
        var field = data.label;
        var cut = field.indexOf(' : ');
        // a Custom Objects field already comes as "<object> : <field>" from its filter label
        if (!group && cut > 0) { group = field.slice(0, cut); field = field.slice(cut + 3); }
        var $input = $('<input type="text" class="form-control patches-field-input" autocomplete="off" tabindex="-1">').val(field).attr('title', (group ? group + ': ' : '') + field);
        $input.on('keydown paste cut drop beforeinput', blockTyping);
        var $box = $('<div class="input-group patches-field-group"></div>');
        if (group) { $box.append($('<span class="input-group-addon"></span>').append($('<b></b>').text(group))); }

        return $box.append($input);
    }

    function badges($) {
        var $actions = $('<div class="patches-filter-actions"></div>');
        BADGES.forEach(function (def) {
            $('<a href="javascript: void(0);" role="button" class="patches-badge patches-filter-badge ' + def.css + '"></a>')
                .attr({'aria-label': def.label, 'data-action': def.key})
                .append('<i class="' + def.icon + '" aria-hidden="true"></i>')
                .append($('<span class="patches-icon-badge__text"></span>').text(def.label))
                .appendTo($actions);
        });

        return $actions;
    }

    function newRow($, $panel, data) {
        var $row = $('<div class="panel patches-sf-row"></div>');
        var $body = $('<div class="panel-body"></div>').appendTo($row);
        // the cells and the badges are one line (main), the alerts of the value go below it
        var $main = $('<div class="patches-sf-main"></div>').appendTo($body);
        // press and hold this badge to move the filter (the only drag handle of the row); on hover it opens and pushes the other cells
        $main.append($('<a href="javascript: void(0);" role="button" class="patches-badge patches-badge--neutral patches-filter-badge patches-sf-drag" data-action="drag" aria-label="Mover"></a>')
            .append('<i class="ri-expand-up-down-line" aria-hidden="true"></i>').append('<span class="patches-icon-badge__text">Mover</span>'));
        $main.append('<div class="patches-sf-glue"><select class="form-control not-chosen" data-patches-sf="glue"></select></div>');
        $main.append($('<div class="patches-sf-field"></div>').append(fieldCell($, data)));
        $main.append('<div class="patches-sf-operator"><select class="form-control not-chosen" data-patches-sf="operator"></select></div>');
        $main.append('<div class="patches-sf-value"><div class="patches-sf-valuecell"></div></div>');
        // the badges have a column of their own (three badges and their gaps wide), after the value
        $main.append(badges($));
        $body.append('<div class="patches-sf-alerts"></div>');
        $row[0].patchesPanel = $panel[0];
        rowOf.set($panel[0], $row[0]);

        return $row;
    }

    function fillSelect($, $select, options, value) {
        var signature = options.map(function (o) { return o.value + '|' + o.text; }).join('||');
        if ($select.data('signature') !== signature) {
            $select.data('signature', signature).empty();
            options.forEach(function (o) { $select.append($('<option></option>').val(o.value).text(o.text)); });
        }
        $select.val(value);
    }

    function syncAlerts($, $row) {
        var $source = $row.find('.patches-sf-valuecell .alert');
        var signature = $source.map(function () { return $(this).attr('class') + '|' + $(this).html(); }).get().join('||');
        var $box = $row.find('.patches-sf-alerts');
        if ($box.data('signature') === signature) { return; }
        $box.data('signature', signature).empty();
        $source.each(function () { $box.append($(this).clone().removeAttr('id')); });
    }

    function drawRow($, $panel, $list, index) {
        var data = Form.readRow($panel);
        var row = rowOf.get($panel[0]);
        var $row = row ? $(row) : newRow($, $panel, data);
        fillSelect($, $row.find('select[data-patches-sf="glue"]'), data.glues, data.glue);
        fillSelect($, $row.find('select[data-patches-sf="operator"]'), data.operators, data.operator);
        $row.find('.patches-sf-glue').toggleClass('is-first', data.first);
        $row.toggleClass('in-group', data.inGroup);
        $row.attr('data-num', data.num);
        $row.find('.patches-filter-badge[data-action="copy"]').toggleClass('patches-off', !data.canCopy);
        var $cell = $row.find('.patches-sf-valuecell');
        var $value = Form.valueOf($panel);
        if ($value.length && !$value.parent().is($cell)) { $cell.append($value); }
        syncAlerts($, $row);
        // moved only when it is not in its place: the observer of the page runs this again after every change of the page
        var $rowsNow = $list.children('.patches-sf-row');
        if ($rowsNow.get(index) !== $row[0]) { if ($rowsNow.eq(index).length) { $rowsNow.eq(index).before($row); } else { $list.append($row); } }
    }

    function render($) {
        if (!Form.active($) || dragging) { return; }
        var $core = $(Form.LIST);
        var $pane = $core.parent();
        var $list = $core.prev('.patches-sf');
        if (!$list.length) {
            $list = $('<div class="patches-sf"></div>').insertBefore($core);
            sortable($, $list);
        }
        var $panels = Form.panels($);
        // rows of panels that are gone (removed, or the form was drawn again) leave; the others are drawn in the order of the panels
        $list.children('.patches-sf-row').each(function () {
            var $linked = $(this.patchesPanel);
            if (!$linked.parent().is($core) || $linked.attr('data-patches-removing')) { $(this).remove(); }
        });
        $panels.each(function (index) { drawRow($, $(this), $list, index); });
        // an AND filter is glued to the one above it (one frame around the group): the flags only change borders and gaps
        var $rows = $list.children('.patches-sf-row');
        $rows.each(function (index) {
            var up = $(this).hasClass('in-group');
            var down = $rows.eq(index + 1).hasClass('in-group');
            $(this).toggleClass('patches-sf-join-up', up).toggleClass('patches-sf-join-down', down);
        });
        heads($, $list, $rows);
        stickyUpdate($);
        $pane.addClass('patches-sf-on');
    }

    // ---- group headers (the column titles of each group of filters; the one of the group on screen sticks under the top bar until the group ends) ----

    function headMarkup() {
        return '<div class="patches-sf-head" aria-hidden="true"><div class="patches-sf-head__main"><span class="patches-sf-head__move"></span>'
            + '<span class="patches-sf-head__glue">Operador</span><span class="patches-sf-head__field">Campo</span>'
            + '<span class="patches-sf-head__operator">Condição</span><span class="patches-sf-head__value">Valor</span><span class="patches-sf-head__actions"></span></div></div>';
    }

    /** One header before the first filter of every group (a filter that is not AND-glued starts one). Only what differs is touched: the page observer runs this after every change. */
    function heads($, $list, $rows) {
        $list.children('.patches-sf-head').each(function () {
            var $next = $(this).next();
            if (!$next.hasClass('patches-sf-row') || $next.hasClass('in-group')) { $(this).remove(); }
        });
        $rows.each(function () {
            if (!$(this).hasClass('in-group') && !$(this).prev().hasClass('patches-sf-head')) { $(this).before(headMarkup()); }
        });
    }

    var stickyBound = false;

    function topOffset($) {
        var bar = document.querySelector('.navbar-fixed-top, #app-header, header.app-header, .app-header');
        var bottom = bar ? bar.getBoundingClientRect().bottom : 48;

        return Math.max(0, bottom);
    }

    /** The header of the group on screen is shown fixed under the top bar; when the last filter of the group reaches it, it rides above that filter and leaves with it (it never covers it). */
    function stickyUpdate($) {
        var $list = $('.patches-sf');
        var $sticky = $('.patches-sf-sticky');
        if (!$list.length || !Form.active($) || !$('#filters').hasClass('active')) { $sticky.remove(); return; }
        var top = topOffset($);
        var active = null;
        $list.children('.patches-sf-head').each(function () {
            var head = this;
            var last = $(head).nextUntil('.patches-sf-head', '.patches-sf-row').last()[0];
            if (!last) { return; }
            if (head.getBoundingClientRect().top < top && last.getBoundingClientRect().bottom > top) { active = {head: head, last: last}; }
        });
        if (!active) { $sticky.remove(); return; }
        if (!$sticky.length) {
            $sticky = $(headMarkup()).addClass('patches-sf-sticky').appendTo('body');
        }
        var box = $list[0].getBoundingClientRect();
        var height = $sticky.outerHeight();
        // the header never covers the last filter of its group: it stops above it and leaves with it
        var lastTop = active.last.getBoundingClientRect().top;
        $sticky.css({left: box.left + 'px', width: box.width + 'px', top: Math.min(top, lastTop - height) + 'px'});
    }

    function safeRender($) {
        try {
            render($);
        } catch (e) {
            $(Form.LIST).parent().removeClass('patches-sf-on');
            if (root.console) { root.console.error('segment filters UI', e); }
        }
    }

    // while a row is dragged the list holds the helper and the placeholder: nothing is drawn then
    var dragging = false;

    function sortable($, $list) {
        if (!$list.sortable) { return; }
        $list.sortable({
            items: '> .patches-sf-row',
            handle: '.patches-sf-drag',
            cancel: '',
            tolerance: 'pointer',
            axis: 'y',
            scroll: true,
            start: function () { dragging = true; },
            stop: function () {
                dragging = false;
                var elements = $list.children('.patches-sf-row').map(function () { return this.patchesPanel; }).get();
                Form.order(elements);
            }
        });
    }

    // ---- the add bar (draft) ------------------------------------------------------------------------------------

    /** The select that adds a filter (core's .available-filters, with its chosen) lives in the field slot of the bar: core finds it by id and class, wherever it is. */
    function adoptSelector($, $dock) {
        var $slot = $dock.find('.' + DOCK + '__field');
        var $fresh = $('.available-filters').filter(function () { return !$(this).closest('.' + DOCK).length; }).first();
        if (!$fresh.length) { return; }
        $slot.empty().append($fresh);
    }

    function dock($) {
        var $dock = $('.' + DOCK);
        if (!$(Form.LIST).length) { $dock.remove(); return; }
        if ($dock.length) {
            // the form was drawn again (Apply, ajax load): the draft belongs to the old form and the select of the new one is outside the bar
            var form = $(Form.LIST)[0];
            if ($dock.data('patchesForm') !== form) {
                $dock.data('patchesForm', form);
                adoptSelector($, $dock);
                clearDraft($);
            } else if (!$dock.find('.available-filters').length) {
                adoptSelector($, $dock);
            }
            return;
        }
        // a core .panel with a .panel-body, like a filter, so the theme of the user paints it the same way
        var $row = $('<div class="panel ' + DOCK + '" aria-hidden="true"></div>');
        var $body = $('<div class="panel-body"></div>').appendTo($row);
        $body.append('<div class="' + DOCK + '__glue"><select class="form-control not-chosen patches-dock-placeholder"><option value="and">e</option><option value="or">ou</option></select></div>');
        $body.append('<div class="' + DOCK + '__field"></div>');
        $body.append('<div class="' + DOCK + '__condition"><div class="col-sm-3 padding-none patches-dock-placeholder"><select class="form-control not-chosen" disabled="disabled"><option>condição</option></select></div></div>');
        var $value = $('<div class="' + DOCK + '__value"></div>').appendTo($body);
        $value.append('<div class="col-xs-12 col-sm-6 padding-none patches-dock-placeholder"><div class="properties-form"><div><div class="row"><div class="form-group col-xs-12"><input type="text" class="form-control" readonly="readonly" placeholder="valor"></div></div></div></div></div>');
        $value.append('<button type="button" class="btn btn-primary btn-nospin patches-filter-dock__add"><i class="ri-add-line" aria-hidden="true"></i> Adicionar</button>');
        $row.append('<div class="' + DOCK + '__draft" hidden></div>');
        $row.append('<div class="' + DOCK + '__alerts"></div>');
        $('body').append($row);
        $row.data('patchesForm', $(Form.LIST)[0]);
        adoptSelector($, $row);
    }

    /**
     * The draft: choosing a field in the bar does not add the filter to the list. Core builds the filter as always
     * (Mautic.addLeadListFilter); that panel is taken out of the list and kept in the bar, and its glue, operator and value
     * widgets are shown in the slots. "Adicionar" puts the panel back into the list (renumbered by core) and clears the bar.
     */
    function slots($) {
        var $dock = $('.' + DOCK);
        return {
            dock: $dock,
            glue: $dock.find('.' + DOCK + '__glue'),
            condition: $dock.find('.' + DOCK + '__condition'),
            value: $dock.find('.' + DOCK + '__value'),
            draft: $dock.find('.' + DOCK + '__draft')
        };
    }

    function clearDraft($) {
        var s = slots($);
        s.glue.children().not('.patches-dock-placeholder').remove();
        s.glue.children('.patches-dock-placeholder').show();
        s.condition.children().not('.patches-dock-placeholder').remove();
        s.value.children('.col-sm-6').not('.patches-dock-placeholder').remove();
        s.draft.empty();
        s.value.children('.patches-filter-dock__add').prop('disabled', false).removeClass('disabled');
        s.condition.children('.patches-dock-placeholder').show();
        s.value.children('.patches-dock-placeholder').show();
        s.dock.find('#available_segment_filters').val('').trigger('chosen:updated');
    }

    /** "Adicionar" waits for the value form core loads by ajax: committing before it arrives would renumber the draft and the late answer would miss it. */
    function valueReady($) {
        if (Form.draftPending()) { return false; }
        var s = slots($);
        var $properties = s.value.children('.col-sm-6').not('.patches-dock-placeholder').first();
        return !!s.draft.children('.segment-filter').length && $properties.find('input, select, textarea').length > 0;
    }

    function syncAdd($) {
        var s = slots($);
        var pending = !!s.draft.children('.segment-filter').length && !valueReady($);
        s.value.children('.patches-filter-dock__add').prop('disabled', pending).toggleClass('disabled', pending);
    }

    function startDraft($, optionSelector) {
        var s = slots($);
        var $panel = $(Form.LIST).children('.segment-filter').last();
        if (!s.dock.length || !$panel.length) { return; }
        $panel.detach();
        var $body = $panel.children('.panel-body');
        var $glue = $panel.find('.panel-glue').first().removeAttr('onchange');
        $glue.find('select').removeAttr('onchange');
        $glue.removeClass('hide');
        s.glue.children('.patches-dock-placeholder').hide();
        s.glue.append($glue);
        var $operator = $body.children('.col-sm-3.padding-none').first();
        s.condition.children('.patches-dock-placeholder').hide();
        s.condition.append($operator);
        var $properties = $body.children('.col-sm-6').first();
        s.value.children('.patches-dock-placeholder').hide();
        s.value.children('.patches-filter-dock__add').before($properties);
        s.draft.append($panel);
        syncAdd($);
        // core clears the select right after this call: show the chosen field again
        setTimeout(function () {
            $(optionSelector).prop('selected', true);
            s.dock.find('#available_segment_filters').trigger('chosen:updated');
        }, 0);
    }

    function commitDraft($, Mautic) {
        var s = slots($);
        var $panel = s.draft.children('.segment-filter').first();
        if (!$panel.length || !valueReady($)) { return; }
        var $body = $panel.children('.panel-body');
        var $glue = s.glue.children('.panel-glue').first();
        var $operator = s.condition.children('.col-sm-3.padding-none').not('.patches-dock-placeholder').first();
        var $properties = s.value.children('.col-sm-6').not('.patches-dock-placeholder').first();
        $body.prepend($glue);
        $body.children('.field-name').first().after($operator);
        $operator.after($properties);
        $panel.appendTo(Form.LIST);
        // as core: the glue of the first filter is hidden
        $glue.toggleClass('hide', !$panel.prevAll('.segment-filter').length);
        $glue.find('select').attr('onchange', 'Mautic.updateFilterPositioning(this)');
        Mautic.reorderSegmentFilters();
        Mautic.updateFilterPositioning($glue.find('select').first());
        Mautic.segmentFilter().showCopyBasedOnGlue($panel);
        clearDraft($);
        refresh($);
        if (typeof root.mauticPatchesInChipsScan === 'function') { root.mauticPatchesInChipsScan(); }
    }

    /** The Filters tab gets a bottom padding a quarter as high as the bar plus its 56px distance from the window bottom and a fixed room (72px) for the floating alert, so the bar never covers the last filter. */
    function reserveSpace($) {
        var $dock = $('.' + DOCK);
        var height = $dock.length ? Math.ceil($dock.outerHeight() / 4 + 56 + 16 + ALERT_SPACE) : 0;
        document.documentElement.style.setProperty('--patches-dock-space', height + 'px');
        if ($dock.length && !$dock.data('patchesObserved') && typeof ResizeObserver !== 'undefined') {
            $dock.data('patchesObserved', true);
            new ResizeObserver(function () { reserveSpace($); }).observe($dock[0]);
        }
    }

    function showOnlyOnFiltersTab($) {
        $('.' + DOCK).toggleClass('patches-dock-off', !$('#filters').hasClass('active'));
    }

    /** The alerts the value widget of the bar brings (e.g. the "na lista" hint) float above the bar, with its width; the ones inside the widget are hidden by CSS. */
    function syncDockAlerts($) {
        var $box = $('.' + DOCK).find('.' + DOCK + '__alerts');
        if (!$box.length) { return; }
        var $source = $('.' + DOCK).find('.' + DOCK + '__value .alert');
        var signature = $source.map(function () { return $(this).attr('class') + '|' + $(this).html(); }).get().join('||');
        if ($box.data('signature') === signature) { return; }
        $box.data('signature', signature).empty();
        $source.each(function () { $box.append($(this).clone().removeAttr('id')); });
    }

    function refresh($) {
        if (!Form.active($)) { $('.' + DOCK).remove(); return; }
        dock($);
        syncDockAlerts($);
        syncAdd($);
        showOnlyOnFiltersTab($);
        reserveSpace($);
        safeRender($);
    }

    var draftSeq = 0;
    var draftNumber = null;

    function install($, Mautic) {
        if (root.mauticPatchesSegmentFilterUi) { return; }
        root.mauticPatchesSegmentFilterUi = true;
        Form = root.MauticPatchesSegmentFilterForm;
        Form.install($, Mautic, function () { safeRender($); });

        var panelOf = function (el) { return $(el).closest('.patches-sf-row')[0].patchesPanel; };
        var $panelOf = function (el) { return $(panelOf(el)); };

        $(document).on('change', '.patches-sf-row select[data-patches-sf="glue"]', function () { Form.setGlue($panelOf(this), $(this).val()); });
        $(document).on('change', '.patches-sf-row select[data-patches-sf="operator"]', function () { Form.setOperator($panelOf(this), $(this).val()); });
        $(document).on('click', '.patches-sf-row .patches-filter-badge', function (event) {
            event.preventDefault();
            var $panel = $panelOf(this);
            var action = $(this).attr('data-action');
            if (action === 'duplicate') { Form.duplicate($panel); } else if (action === 'copy') { Form.copyGroup($panel); } else if (action === 'remove') { Form.remove($panel); }
        });

        $(document).on('keydown paste cut drop beforeinput', '.patches-field-input', blockTyping);
        if (!stickyBound) { stickyBound = true; root.addEventListener('scroll', function () { stickyUpdate($); }, true); root.addEventListener('resize', function () { stickyUpdate($); }); }

        var segmentFilter = Mautic.segmentFilter;
        Mautic.segmentFilter = function () {
            var api = segmentFilter.apply(this, arguments);
            if (draftNumber !== null) { api.getFilterCount = function () { return draftNumber; }; }

            return api;
        };

        // the bar's select adds a draft, not a filter of the list
        var addFilter = Mautic.addLeadListFilter;
        Mautic.addLeadListFilter = function (elId, elObj) {
            var inBar = $('#available_' + elObj + '_' + elId).closest('.' + DOCK).length > 0;
            // the e / ou the user chose survives the change of field
            var glue = inBar ? slots($).glue.find('select:visible, select.glue-select').first().val() : null;
            if (inBar) { clearDraft($); }
            // the draft gets a number no filter of the list has, so its ids, and the answer of its ajax, never meet another filter
            if (inBar) { draftNumber = Form.DRAFT_BASE + (++draftSeq); }
            var result;
            try { result = addFilter.apply(this, arguments); } finally { draftNumber = null; }
            if (inBar) {
                startDraft($, '#available_' + elObj + '_' + elId);
                if (glue) { slots($).glue.find('select.glue-select').val(glue); }
            }

            return result;
        };
        Form.onSettled(function () { syncAdd($); });
        $(document).on('click', '.patches-filter-dock__add', function (event) {
            event.preventDefault();
            commitDraft($, Mautic);
        });
        // a draft's operator change starts a value form request: the button waits for it
        $(document).on('change', '.' + DOCK + '__condition select', function () { setTimeout(function () { syncAdd($); }, 0); });

        $(document).on('shown.bs.tab click', 'a[data-toggle="tab"]', function () { setTimeout(function () { showOnlyOnFiltersTab($); }, 0); });

        var run = function () { refresh($); };
        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        $(document).ajaxComplete(run);
        $(run);
        if (typeof MutationObserver !== 'undefined') {
            // a panel added or removed by core (not the deep changes: the rows are ours)
            new MutationObserver(function () { if (Form.active($)) { run(); } }).observe(document.body, {childList: true, subtree: true});
        }
    }

    var api = {install: install, LABEL: LABEL, BADGES: BADGES};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.MauticPatchesSegmentFilterUi = api;
    }
})(typeof window !== 'undefined' ? window : this);
