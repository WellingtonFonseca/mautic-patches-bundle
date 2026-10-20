/*
 * The rows of the campaign condition "Contact fields": each row (the first criterion and the added ones) follows its own
 * field. The select of the field carries, per option, data-operators (operator => label), data-options (value => label, a
 * list field), data-field-type and data-object. A change of the field or of the operator rebuilds the operator list and the
 * value control: a text input, a select (list field), a multiple select for including / excluding on a list (the value is
 * kept in a hidden input as A|B|C, which is what the condition stores) or a calendar (date). Including / excluding on a
 * text field gets its chips from segment-in-chips.js, which listens to cf:criterion-updated.
 * The decisions are in plain functions tested in Node (Tests/js/contact-fields-condition.test.js); the DOM part is
 * checked in the browser.
 */
(function (root) {
    'use strict';

    var EMPTY_OPERATORS = ['empty', '!empty'];
    var LIST_OPERATORS  = ['in', '!in'];
    var TEXT_ONLY       = ['regexp', '!regexp', 'like', '!like', 'startsWith', 'endsWith', 'contains'];

    // what the value control of a row is, for a field (its options) and an operator
    function controlFor(options, operator) {
        if (EMPTY_OPERATORS.indexOf(operator) !== -1) { return 'disabled'; }
        var hasOptions = options && Object.keys(options).length > 0;
        if (!hasOptions || TEXT_ONLY.indexOf(operator) !== -1) { return 'text'; }

        return LIST_OPERATORS.indexOf(operator) !== -1 ? 'multi' : 'select';
    }

    // the operator that stays selected when the field changes: the same one if the new field offers it, else the first
    function keepOperator(operators, current) {
        var keys = Object.keys(operators);

        return keys.indexOf(current) !== -1 ? current : (keys[0] || '');
    }

    function split(value) {
        return String(value == null ? '' : value).split('|').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
    }

    var api = {controlFor: controlFor, keepOperator: keepOperator, split: split};

    function install($) {
        var Mautic = root.Mautic;

        function row($el) { return $el.closest('.cf-criterion'); }

        function update($row) {
            var $field    = $row.find('.cf-criterion-field');
            var $operator = $row.find('.cf-criterion-operator');
            var $selected = $field.find(':selected');
            var operators = JSON.parse($selected.attr('data-operators') || '{}');
            var options   = JSON.parse($selected.attr('data-options') || '{}');
            var type      = $selected.attr('data-field-type');
            var operator  = keepOperator(operators, $operator.val());

            $operator.empty();
            Object.keys(operators).forEach(function (key) {
                $('<option/>').attr('value', key).text(operators[key]).prop('selected', key === operator).appendTo($operator);
            });
            $operator.val(operator).trigger('chosen:updated');

            var $old  = $row.find('.cf-criterion-value');
            var value = $old.val();
            var attrs = {'class': $old.attr('class'), id: $old.attr('id'), name: $old.attr('name'), autocomplete: 'off'};
            var kind  = controlFor(options, operator);

            $row.find('.cf-criterion-multi, .in-chips-part').remove();
            if (Mautic && $old.is('select')) { Mautic.destroyChosen($old); }

            var $new;
            if (kind === 'select') {
                $new = $('<select/>');
                Object.keys(options).forEach(function (key) {
                    $('<option/>').attr('value', key).text(options[key]).prop('selected', key === value).appendTo($new);
                });
                $new.val(options.hasOwnProperty(value) ? value : null);
            } else if (kind === 'multi') {
                var chosen = split(value);
                $new = $('<input type="hidden"/>').val(chosen.join('|'));
                var $multi = $('<select multiple class="form-control cf-criterion-multi"/>').css('height', '120px');
                Object.keys(options).forEach(function (key) {
                    $('<option/>').attr('value', key).text(options[key]).prop('selected', chosen.indexOf(key) !== -1).appendTo($multi);
                });
                $multi.on('change', function () { $new.val(($multi.val() || []).join('|')); });
                $old.after($multi);
            } else {
                $new = $('<input type="text"/>');
                if (kind === 'disabled') {
                    $new.attr('disabled', 'disabled').val('');
                } else {
                    $new.val(value);
                }
                if ((type === 'date' || type === 'datetime') && $.fn.datetimepicker) {
                    $new.datetimepicker({
                        timepicker: type === 'datetime', format: type === 'datetime' ? 'Y-m-d H:i' : 'Y-m-d', lazyInit: true,
                        validateOnBlur: false, allowBlank: true, scrollMonth: false, scrollInput: false, closeOnDateSelect: type === 'date'
                    });
                }
            }

            $new.attr(attrs);
            $old.replaceWith($new);
            // the multiple select goes right after the hidden input, in the same cell
            $row.find('.cf-criterion-multi').insertAfter($row.find('.cf-criterion-value'));
            $(root.document).trigger('cf:criterion-updated');
        }

        function initRow($row) {
            if (!$row.find('.cf-criterion-field').length || $row.data('cf-init')) { return; }
            $row.data('cf-init', true);
            update($row);
            $row.find('.cf-criterion-field').on('change', function () { update($row); });
            $row.find('.cf-criterion-operator').on('change', function () { update($row); });
        }

        root.mauticPatchesContactFields = {
            init: function () {
                $('form[name=campaignevent] #cf-criteria .cf-criterion').each(function () { initRow($(this)); });
            },
            add: function () {
                var $box = $('#cf-criteria');
                var index = parseInt($box.attr('data-index'), 10);
                var $row = $($box.attr('data-prototype').replace(/__name__/g, index));
                $box.attr('data-index', index + 1);
                $('#cf-criteria-extra').append($row);
                initRow($row);
            },
            remove: function (link) { row($(link)).remove(); }
        };
    }

    api.install = install;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesContactFieldsApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
