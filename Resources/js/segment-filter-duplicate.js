/*
 * "Duplicar condição" on every filter of the segment form: a button next to core's copy and remove ones that adds a copy
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
    var LABEL   = 'Duplicar condição';

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
        $clone.find('.segment-filter-duplicate').remove();
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

    function addButton($, $filter) {
        var $group = $filter.find('.segment-button').first();
        if (!$group.length || $group.find('.segment-filter-duplicate').length) { return; }
        var $button = $('<a href="javascript: void(0);" class="btn btn-ghost btn-icon btn-nospin segment-filter-duplicate" size="md" role="button"></a>')
            .attr({'aria-label': LABEL, title: LABEL})
            .append('<i class="ri-add-box-line" aria-hidden="true"></i>');
        $group.prepend($button);
    }

    /** The three buttons of a filter (duplicate, core's copy, remove) at the size of the Custom Objects field buttons: md, large icon. */
    function enlarge($filter) {
        $filter.find('.segment-button .btn').attr('size', 'md').find('i').addClass('ri-lg');
    }

    function scan($) {
        $(FILTERS + ' .segment-filter').each(function () { addButton($, $(this)); enlarge($(this)); });
    }

    function install($, Mautic) {
        if (root.mauticPatchesFilterDuplicate) { return; }
        root.mauticPatchesFilterDuplicate = true;

        // one handler for every filter, present or added later
        $(document).on('click', FILTERS + ' .segment-filter-duplicate', function (event) {
            event.preventDefault();
            duplicate($, Mautic, $(this).closest('.segment-filter'));
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

    var api = {install: install, copyOf: copyOf, addButton: addButton, enlarge: enlarge, LABEL: LABEL};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesFilterDuplicateApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
