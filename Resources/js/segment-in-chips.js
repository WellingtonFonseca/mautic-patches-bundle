/*
 * Including / excluding on a text filter of the segment form: the value input becomes chips (one per value), with
 * suggestions from the values already stored, and any other value can still be typed (Enter). The input of the form
 * stays, hidden, and holds the values joined with | (A|B|C), which is what core's "in" reads: nothing changes on the server.
 * Logic below is tested in Node (Tests/js/segment-in-chips.test.js); the widget is the DOM part.
 */
(function (root) {
    'use strict';

    var SEPARATOR = '|';

    function parse(stored) {
        return add([], stored == null ? '' : String(stored));
    }

    function join(values) {
        return values.join(SEPARATOR);
    }

    // What is typed or pasted: split on the separator, new lines and tabs (a pasted column); comma and semicolon stay.
    function add(values, raw) {
        var result = values.slice();
        String(raw).split(/[|\r\n\t]+/).forEach(function (part) {
            part = part.trim();
            if (part !== '' && result.indexOf(part) === -1) {
                result.push(part);
            }
        });

        return result;
    }

    function remove(values, index) {
        return values.filter(function (_, i) { return i !== index; });
    }

    function suggestions(list, chosen) {
        return list.filter(function (value) { return chosen.indexOf(value) === -1; });
    }

    function url(cfg, object, field, query) {
        var tail = '&q=' + encodeURIComponent(query) + '&limit=20';
        if (object === 'lead' || object === 'company') {
            return cfg.valuesUrl + '?object=' + object + '&field=' + encodeURIComponent(field) + tail;
        }
        if (object === 'custom_object' && cfg.customUrl) {
            return cfg.customUrl + '?field=' + encodeURIComponent(field) + tail;
        }

        return null;
    }

    function wants(operator, inputType) {
        return (operator === 'in' || operator === '!in') && inputType === 'text';
    }

    var api = {parse: parse, join: join, add: add, remove: remove, suggestions: suggestions, url: url, wants: wants};

    // ---- the widget (DOM; mQuery is Mautic's jQuery) ----
    function enhance($, input, cfg) {
        var $input   = $(input);
        var id       = $input.attr('id') || '';
        var row      = /^(leadlist_filters_\d+)_properties_filter$/.exec(id);
        var object   = $('#' + row[1] + '_object').val();
        var field    = $('#' + row[1] + '_field').val();
        var values   = parse($input.val());
        var $box     = $('<div class="form-control segment-in-chips" style="height:auto;min-height:34px;padding:3px 6px;position:relative;cursor:text"></div>');
        var $typing  = $('<input type="text" autocomplete="off" style="border:0;outline:0;background:transparent;color:inherit;min-width:140px;width:auto;flex:1">');
        var $menu    = $('<ul class="dropdown-menu" style="display:none;max-height:240px;overflow:auto;width:100%;left:0;top:100%"></ul>');
        var request  = null;
        var timer    = null;
        var active   = -1;

        $input.hide().attr('data-in-chips', '1').after($box);
        $box.css({display: 'flex', flexWrap: 'wrap', gap: '4px', alignItems: 'center'});

        function store() {
            $input.val(join(values)).trigger('change');
        }

        function draw() {
            $box.children('.segment-in-chip').remove();
            values.forEach(function (value, i) {
                var $chip = $('<span class="label label-gray segment-in-chip" style="font-size:12px;padding:4px 6px;font-weight:normal;display:inline-flex;align-items:center;gap:4px"></span>').text(value);
                $('<a href="#" style="color:inherit;text-decoration:none" title="×">×</a>').on('click', function (e) {
                    e.preventDefault();
                    values = remove(values, i);
                    store();
                    draw();
                }).appendTo($chip);
                $chip.insertBefore($typing);
            });
        }

        function close() {
            $menu.hide().empty();
            active = -1;
        }

        function commit(raw) {
            var next = add(values, raw);
            if (next.length !== values.length) {
                values = next;
                store();
                draw();
            }
            $typing.val('');
            close();
        }

        function show(list) {
            $menu.empty();
            suggestions(list, values).forEach(function (value) {
                $('<li><a href="#"></a></li>').find('a').text(value).on('mousedown', function (e) {
                    e.preventDefault();
                    commit(value);
                    $typing.focus();
                }).end().appendTo($menu);
            });
            $menu.toggle($menu.children().length > 0);
            active = -1;
        }

        function ask() {
            var target = url(cfg, object, field, $typing.val());
            if (!target) { return; }
            if (request) { request.abort(); }
            request = $.getJSON(target).done(function (data) { show(data.values || []); });
        }

        $typing.on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(ask, 250);
        }).on('focus', ask).on('keydown', function (e) {
            var $items = $menu.children();
            if (e.which === 13) {
                e.preventDefault();
                commit(active >= 0 ? $items.eq(active).text() : $typing.val());
            } else if (e.which === 8 && $typing.val() === '' && values.length) {
                values = values.slice(0, -1);
                store();
                draw();
            } else if (e.which === 27) {
                close();
            } else if ((e.which === 40 || e.which === 38) && $items.length) {
                e.preventDefault();
                active = (active + (e.which === 40 ? 1 : -1) + $items.length) % $items.length;
                $items.removeClass('active').eq(active).addClass('active');
            }
        }).on('paste', function (e) {
            var data = (e.originalEvent || e).clipboardData;
            if (data) {
                e.preventDefault();
                commit(data.getData('text'));
            }
        }).on('blur', function () {
            // What is typed and not confirmed is kept, so it is not lost when the user goes straight to Save.
            commit($typing.val());
        });

        $box.on('click', function () { $typing.focus(); }).append($typing).append($menu);
        draw();
    }

    function scan($, cfg) {
        $('input[id$="_properties_filter"]').each(function () {
            var $input = $(this);
            var row    = /^(leadlist_filters_\d+)_properties_filter$/.exec($input.attr('id') || '');
            if (!row || $input.attr('data-in-chips')) { return; }
            if (wants($('#' + row[1] + '_operator').val(), $input.attr('type'))) {
                enhance($, this, cfg);
            }
        });
    }

    function install($, Mautic, cfg) {
        if (root.mauticPatchesInChips) { return; }
        root.mauticPatchesInChips = true;

        var run = function () { scan($, cfg); };
        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        // The value input is replaced by an ajax answer whenever the operator, the field or the filter changes.
        $(document).ajaxComplete(run);
        $(run);
    }

    api.install = install;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesInChipsApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
