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
        {key: 'copy', icon: 'ri-file-copy-line', css: 'patches-badge--locked', label: 'Clone', core: '.copy-filter-group'},
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
     * The field name as a read only input: "Contato: <field>", "Empresa: <field>"; a Custom Objects field already comes as
     * "<object> : <field>" from its filter label, so it stays as it is.
     */
    function nameInput($, $filter) {
        var $name = $filter.find('.panel-body .field-name').first();
        if (!$name.length || $name.find('.patches-field-input').length) { return; }
        var label = $.trim($name.text());
        var object = $filter.find('input[name$="[object]"]').first().val();
        var text = OWNERS[object] ? OWNERS[object] + ': ' + label : label;
        $name.empty().append($('<input type="text" class="form-control patches-field-input" readonly="readonly">').val(text).attr('title', text));
    }

    function addButton($, $filter) {
        moveGlue($filter);
        nameInput($, $filter);
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

    /** A floating bar at the bottom centre of the segment form with the first row of a filter (E/OU, field, condition, value, badges). Only the layout: no field is filled, nothing is searched, no action is bound. */
    function dock($) {
        var $dock = $('.' + DOCK);
        if (!$(FILTERS).length) { $dock.remove(); return; }
        if ($dock.length) { return; }
        var $row = $('<div class="' + DOCK + '" aria-hidden="true"></div>');
        $row.append('<div class="' + DOCK + '__glue"></div>');
        $row.append('<div class="' + DOCK + '__field"><input type="text" class="form-control" readonly="readonly" placeholder="Contato: campo"></div>');
        $row.append('<div class="' + DOCK + '__condition"><select class="form-control" disabled="disabled"><option>condição</option></select></div>');
        var $value = $('<div class="' + DOCK + '__value"></div>').appendTo($row);
        $value.append('<input type="text" class="form-control" readonly="readonly" placeholder="valor">');
        var $actions = $('<div class="patches-filter-actions"></div>').appendTo($value);
        BADGES.forEach(function (def) {
            $actions.append($('<span class="patches-badge patches-filter-badge ' + def.css + '"></span>')
                .append('<i class="' + def.icon + '" aria-hidden="true"></i>')
                .append($('<span class="patches-icon-badge__text"></span>').text(def.label || LABEL)));
        });
        $('body').append($row);
    }

    function scan($) {
        dock($);
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

    var api = {install: install, copyOf: copyOf, addButton: addButton, moveGlue: moveGlue, nameInput: nameInput, LABEL: LABEL};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesFilterDuplicateApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
