/*
 * "Duplicar condição" on every filter of the segment form: a badge (with core's copy and remove ones, all three to the right of the value input) that adds a copy
 * of THAT filter (same field, operator, value and AND / OR) right below it, so the same condition for another value
 * (disciplina not equal XXXX, then YYYY) does not need the field to be searched again. Core's own copy button copies a
 * whole OR group to the end of the list and is hidden on an AND filter, so it does not do this.
 *
 * The copy is made like core's (_cloneFilter in lead.js): the selects are cloned with their selected option, the names
 * and ids are renumbered by Mautic.reorderSegmentFilters, the properties form is started again by
 * Mautic.triggerOnPropertiesFormLoadedEvent and core's events are attached by Mautic.segmentFilter().attachEvents.
 * The including / excluding chips (segment-in-chips.js) are DOM added to the input, not part of the form: they are
 * removed from the copy and drawn again from its input.
 */
(function (root) {
    'use strict';

    var FILTERS = '#leadlist_filters';
    var LABEL   = 'Duplicar';

    /** The copy of a filter, ready to be put after it. */
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
        $clone.find('.patches-filter-actions').remove();
        $clone.find('.patches-filter-alerts').remove();
        // the first filter has its glue hidden; a copy below it needs it (it is AND, so it stays in the group)
        $clone.find('.panel-glue').removeClass('hide');

        return $clone;
    }

    function duplicate($, Mautic, $origin) {
        var $clone = copyOf($, $origin);
        $origin.after($clone);
        Mautic.reorderSegmentFilters();
        Mautic.triggerOnPropertiesFormLoadedEvent('#' + $clone.attr('id'));
        Mautic.segmentFilter().attachEvents($clone);
        addButton($, $clone);
        // the chips of a text filter are drawn by a scan that runs after every ajax answer; there was no answer here
        if (typeof root.mauticPatchesInChipsScan === 'function') { root.mauticPatchesInChipsScan(); }
        $clone.find('.properties-form input:visible, .properties-form select').first().trigger('focus');

        return $clone;
    }

    /** The three actions of a filter as icon badges (order: core's copy, duplicate, remove). Core's buttons stay, hidden: a badge clicks its core twin so core's handlers run. */
    var BADGES = [
        {key: 'copy', icon: 'ri-add-line', css: 'patches-badge--test', label: 'Clone', core: '.copy-filter-group'},
        {key: 'duplicate', icon: 'ri-add-line', css: 'patches-badge--flag'},
        {key: 'remove', icon: 'ri-subtract-line', css: 'patches-badge--failed', label: 'Remover', core: '.remove-selected'}
    ];

    /** The AND / OR select goes from the heading of the filter to the first place of the filter row (core finds it by class, wherever it is). */
    function moveGlue($filter) {
        var $body = $filter.find('.panel-body').first();
        var $glue = $filter.find('.panel-glue').first();
        if ($body.length && $glue.length && !$glue.parent().is($body)) { $body.prepend($glue); }
    }

    var OWNERS = {lead: 'Contato', company: 'Empresa'};

    /**
     * The field name as a read only input, with its group in bold in front, like the select of the bar: "Contato" | <field>,
     * "Empresa" | <field>; a Custom Objects field already comes as "<object> : <field>" from its filter label, which is split there.
     */
    function nameInput($, $filter) {
        var $name = $filter.find('.panel-body .field-name').first();
        if (!$name.length || $name.find('.patches-field-input').length) { return; }
        var label = $.trim($name.text());
        var object = $filter.find('input[name$="[object]"]').first().val();
        var group = OWNERS[object] || '';
        var field = label;
        var cut = label.indexOf(' : ');
        if (!group && cut > 0) { group = label.slice(0, cut); field = label.slice(cut + 3); }
        var $input = $('<input type="text" class="form-control patches-field-input" readonly="readonly">').val(field).attr('title', (group ? group + ': ' : '') + field);
        var $box = $('<div class="input-group patches-field-group"></div>');
        if (group) { $box.append($('<span class="input-group-addon"></span>').append($('<b></b>').text(group))); }
        $name.empty().append($box.append($input));
    }

    /** The alerts of the value widget of a filter of the list go to the bottom of its panel-body (below the fields, from the field name to the end); the ones below the value are hidden by CSS. */
    function filterAlerts($, $filter) {
        var $body = $filter.children('.panel-body').first();
        if (!$body.length) { return; }
        var $source = $body.find('.properties-form .alert');
        var signature = $source.map(function () { return $(this).attr('class') + '|' + $(this).html(); }).get().join('||');
        var $box = $body.children('.patches-filter-alerts');
        if (!$box.length) {
            if (!signature) { return; }
            $box = $('<div class="patches-filter-alerts"></div>').appendTo($body);
        }
        if ($box.data('signature') === signature) { return; }
        $box.data('signature', signature).empty();
        $source.each(function () { $box.append($(this).clone().removeAttr('id')); });
    }

    function addButton($, $filter) {
        moveGlue($filter);
        nameInput($, $filter);
        filterAlerts($, $filter);
        var $properties = $filter.find('.properties-form').first();
        if (!$properties.length) { return; }
        var $row = $properties.parent('.patches-filter-row');
        if (!$row.length) { $row = $properties.wrap('<div class="patches-filter-row"></div>').parent(); }
        var $actions = $row.children('.patches-filter-actions');
        if (!$actions.length) {
            $actions = $('<div class="patches-filter-actions"></div>').appendTo($row);
            BADGES.forEach(function (def) {
                var label = def.label || LABEL;
                var $badge = $('<a href="javascript: void(0);" role="button" class="patches-badge patches-filter-badge ' + def.css + '"></a>')
                    .attr({'aria-label': label, 'data-action': def.key})
                    .append('<i class="' + def.icon + '" aria-hidden="true"></i>')
                    .append($('<span class="patches-icon-badge__text"></span>').text(label));
                if (def.key === 'duplicate') { $badge.addClass('segment-filter-duplicate'); }
                $actions.append($badge);
            });
        }
        // core hides its copy button on an AND filter: the badge follows it
        var $coreCopy = $filter.find('.segment-button .copy-filter-group').first();
        $actions.children('[data-action="copy"]').toggleClass('hide', !$coreCopy.length || $coreCopy.hasClass('hide'));
    }

    var DOCK = 'patches-filter-dock';
    // room kept for the floating alert (about two lines, 60px) and its 12px gap: fixed, whether an alert is showing or not
    var ALERT_SPACE = 72;

    /** The select that adds a filter (core's .available-filters, with its chosen) lives in the field slot of the bar: core finds it by id and class, wherever it is. */
    function adoptSelector($, $dock) {
        var $slot = $dock.find('.' + DOCK + '__field');
        var $fresh = $('.available-filters').filter(function () { return !$(this).closest('.' + DOCK).length; }).first();
        if (!$fresh.length) { return; }
        $slot.empty().append($fresh);
    }

    /** A floating bar at the bottom centre of the segment form with the first row of a filter (E/OU, field, condition, value, badges). Only the layout: no field is filled, nothing is searched, no action is bound. */
    function dock($) {
        var $dock = $('.' + DOCK);
        if (!$(FILTERS).length) { $dock.remove(); return; }
        if ($dock.length) { return; }
        // a core .panel with a .panel-body, like a filter, so the theme of the user paints it the same way
        var $row  = $('<div class="panel ' + DOCK + '" aria-hidden="true"></div>');
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
        s.condition.children('.patches-dock-placeholder').show();
        s.value.children('.patches-dock-placeholder').show();
        var $select = s.dock.find('#available_segment_filters');
        $select.val('').trigger('chosen:updated');
    }

    function startDraft($, optionSelector) {
        var s = slots($);
        var $panel = $(FILTERS).children('.segment-filter').last();
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
        // core clears the select right after this call: show the chosen field again
        setTimeout(function () {
            $(optionSelector).prop('selected', true);
            s.dock.find('#available_segment_filters').trigger('chosen:updated');
        }, 0);
    }

    function commitDraft($, Mautic) {
        var s = slots($);
        var $panel = s.draft.children('.segment-filter').first();
        if (!$panel.length) { return; }
        var $body = $panel.children('.panel-body');
        var $glue = s.glue.children('.panel-glue').first();
        var $operator = s.condition.children('.col-sm-3.padding-none').not('.patches-dock-placeholder').first();
        var $properties = s.value.children('.col-sm-6').not('.patches-dock-placeholder').first();
        $body.prepend($glue);
        $body.children('.field-name').first().after($operator);
        $operator.after($properties);
        $panel.appendTo(FILTERS);
        $glue.find('select').attr('onchange', 'Mautic.updateFilterPositioning(this)');
        Mautic.reorderSegmentFilters();
        Mautic.updateFilterPositioning($glue.find('select').first());
        Mautic.segmentFilter().showCopyBasedOnGlue($panel);
        clearDraft($);
        scan($);
        if (typeof root.mauticPatchesInChipsScan === 'function') { root.mauticPatchesInChipsScan(); }
    }

    /** The Filters tab gets a bottom padding a quarter as high as the bar plus its 56px distance from the window bottom and a fixed room (72px) for the floating alert, so the bar never covers the last filter. */
    function reserveSpace($) {
        var $dock = $('.' + DOCK);
        var alertSpace = ALERT_SPACE;
        var height = $dock.length ? Math.ceil($dock.outerHeight() / 4 + 56 + 16 + alertSpace) : 0;
        document.documentElement.style.setProperty('--patches-dock-space', height + 'px');
        if ($dock.length && !$dock.data('patchesObserved') && typeof ResizeObserver !== 'undefined') {
            $dock.data('patchesObserved', true);
            var observer = new ResizeObserver(function () { reserveSpace($); });
            observer.observe($dock[0]);
        }
    }

    /** The bar is only for the Filters tab: hidden while another tab of the form is active. */
    function showOnlyOnFiltersTab($) {
        $('.' + DOCK).toggleClass('patches-dock-off', !$('#filters').hasClass('active'));
    }

    /** The alerts the value widget brings (e.g. the "na lista" hint) float above the bar, with its width; the ones inside the widget are hidden by CSS. */
    function syncAlerts($) {
        var $dock = $('.' + DOCK);
        var $box = $dock.find('.' + DOCK + '__alerts');
        if (!$box.length) { return; }
        var $source = $dock.find('.' + DOCK + '__value .alert');
        var signature = $source.map(function () { return $(this).attr('class') + '|' + $(this).html(); }).get().join('||');
        if ($box.data('signature') === signature) { return; }
        $box.data('signature', signature).empty();
        $source.each(function () { $box.append($(this).clone().removeAttr('id')); });
    }

    function scan($) {
        dock($);
        syncAlerts($);
        showOnlyOnFiltersTab($);
        reserveSpace($);
        $(FILTERS + ' .segment-filter').each(function () { addButton($, $(this)); });
    }

    function install($, Mautic) {
        if (root.mauticPatchesFilterDuplicate) { return; }
        root.mauticPatchesFilterDuplicate = true;

        // one handler for every filter, present or added later
        $(document).on('click', FILTERS + ' .segment-filter-duplicate', function (event) {
            event.preventDefault();
            duplicate($, Mautic, $(this).closest('.segment-filter'));
        });

        // a badge of core's own action clicks the hidden core button (copy a group, remove), which has core's handler
        $(document).on('click', FILTERS + ' .patches-filter-badge[data-action="copy"], ' + FILTERS + ' .patches-filter-badge[data-action="remove"]', function (event) {
            event.preventDefault();
            var core = $(this).attr('data-action') === 'copy' ? '.copy-filter-group' : '.remove-selected';
            $(this).closest('.segment-filter').find('.segment-button ' + core).first().trigger('click');
        });

        // the bar's select adds a draft, not a filter of the list
        var addFilter = Mautic.addLeadListFilter;
        Mautic.addLeadListFilter = function (elId, elObj) {
            var inBar = $('#available_' + elObj + '_' + elId).closest('.' + DOCK).length > 0;
            // the e / ou the user chose survives the change of field
            var glue = inBar ? slots($).glue.find('select:visible, select.glue-select').first().val() : null;
            if (inBar) { clearDraft($); }
            var result = addFilter.apply(this, arguments);
            if (inBar) {
                startDraft($, '#available_' + elObj + '_' + elId);
                if (glue) { slots($).glue.find('select.glue-select').val(glue); }
            }

            return result;
        };
        $(document).on('click', '.patches-filter-dock__add', function (event) {
            event.preventDefault();
            commitDraft($, Mautic);
        });

        $(document).on('shown.bs.tab click', 'a[data-toggle="tab"]', function () { setTimeout(function () { showOnlyOnFiltersTab($); }, 0); });

        var run = function () { scan($); };
        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        // a filter added from the selector or copied is a new child of the list
        $(document).ajaxComplete(run);
        $(run);
        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(run).observe(document.body, {childList: true, subtree: true});
        }
    }

    var api = {install: install, copyOf: copyOf, addButton: addButton, moveGlue: moveGlue, nameInput: nameInput, startDraft: startDraft, commitDraft: commitDraft, clearDraft: clearDraft, LABEL: LABEL};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesFilterDuplicateApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
