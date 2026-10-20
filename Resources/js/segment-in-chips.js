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

    // What the copy button puts in the clipboard: one value per line (a column), which add() reads back.
    function lines(values) {
        return values.join('\n');
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

    // the alias the Custom Objects route reads: cmo_<object id> is the item name, cmf_<field id> a field
    function conditionField(picked, objectId) {
        return picked === 'name' ? 'cmo_' + objectId : 'cmf_' + picked;
    }

    function wants(operator, inputType) {
        return (operator === 'in' || operator === '!in') && inputType === 'text';
    }

    var api = {conditionField: conditionField, parse: parse, join: join, lines: lines, add: add, remove: remove, suggestions: suggestions, url: url, wants: wants};

    // ---- the widget (DOM; mQuery is Mautic's jQuery) ----
    // ctx = {object, field, inline}: inline is a condition row whose input already sits in a core input-group (the added rows,
    // with the remove addon), so the chips box and the copy addon join it instead of making a group of their own.
    function enhance($, input, cfg, ctx) {
        var $input   = $(input);
        var object   = ctx.object;
        var field    = ctx.field;
        var values   = parse($input.val());
        var $box     = $('<div class="form-control segment-in-chips" style="height:auto;min-height:34px;padding:3px 6px;position:relative;cursor:text"></div>');
        var $typing  = $('<input type="text" autocomplete="off" style="border:0;outline:0;background:transparent;color:inherit;min-width:140px;width:auto;flex:1">');
        var $menu    = $('<ul class="dropdown-menu" style="display:none;max-height:240px;overflow:auto;width:100%;left:0;top:100%"></ul>');
        var $copy    = $('<span class="input-group-addon postaddon" title="Copiar" style="cursor:pointer"><i class="ri-file-copy-line"></i></span>');
        var $group   = $('<div class="input-group" style="display:flex"></div>');
        var request  = null;
        var timer    = null;
        var active   = -1;

        $input.hide().attr('data-in-chips', '1');
        $box.addClass('in-chips-part');
        $copy.addClass('in-chips-part');
        if (ctx.inline) {
            $input.parent().css('display', 'flex');
            $input.after($box);
            $box.after($copy);
            $input.parent().children('.input-group-addon').css({display: 'flex', alignItems: 'center', width: 'auto'});
        } else {
            $input.after($group);
            $group.append($box).append($copy);
        }
        $box.css({display: 'flex', flexWrap: 'wrap', gap: '4px', alignItems: 'center', flex: '1'});
        $copy.css({display: 'flex', alignItems: 'center', width: 'auto'});

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

        function copy() {
            var text = lines(values);
            var done = function () {
                $copy.attr('title', 'Copiado').find('i').attr('class', 'ri-check-line');
                setTimeout(function () { $copy.attr('title', 'Copiar').find('i').attr('class', 'ri-file-copy-line'); }, 1500);
            };
            if (root.navigator && root.navigator.clipboard && root.isSecureContext) {
                root.navigator.clipboard.writeText(text).then(done);
                return;
            }
            var $area = $('<textarea style="position:fixed;opacity:0"></textarea>').val(text).appendTo('body');
            $area[0].select();
            try { if (root.document.execCommand('copy')) { done(); } } catch (e) { /* nothing to do: the clipboard is blocked */ }
            $area.remove();
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

        $copy.on('click', function (e) { e.preventDefault(); copy(); });
        $box.on('click', function () { $typing.focus(); }).append($typing).append($menu);
        draw();
    }

    function scan($, cfg) {
        $('input[id$="_properties_filter"]').each(function () {
            var $input = $(this);
            var row    = /^(leadlist_filters_\d+)_properties_filter$/.exec($input.attr('id') || '');
            if (!row || $input.attr('data-in-chips')) { return; }
            if (wants($('#' + row[1] + '_operator').val(), $input.attr('type'))) {
                enhance($, this, cfg, {object: $('#' + row[1] + '_object').val(), field: $('#' + row[1] + '_field').val(), inline: false});
            }
        });
        scanCondition($, cfg);
    }

    // The Custom Objects campaign condition: each criterion row has .co-criterion-field (a Custom Field id, or "name" for
    // the item name), .co-criterion-operator and .co-criterion-value. The plugin replaces the value input whenever the
    // field or the operator changes, which leaves the old chips behind: they are removed and the new input is decorated.
    function scanCondition($, cfg) {
        $('form[name=campaignevent] .co-criterion').each(function () {
            var $row   = $(this);
            var $input = $row.find('input.co-criterion-value');
            if (!$input.length) { return; }
            if ($input.attr('data-in-chips')) { return; }
            $row.find('.in-chips-part').remove();
            if (!wants($row.find('.co-criterion-operator').val(), $input.attr('type'))) { return; }
            var picked = $row.find('.co-criterion-field').val();
            var object = $('form[name=campaignevent] input[name$="[customObjectId]"]').val();
            enhance($, $input[0], cfg, {object: 'custom_object', field: conditionField(picked, object), inline: $input.parent().hasClass('input-group')});
        });
    }

    function install($, Mautic, cfg) {
        if (root.mauticPatchesInChips) { return; }
        root.mauticPatchesInChips = true;

        var run = function () { scan($, cfg); };
        // a filter copied by segment-filter-duplicate.js asks for the same scan
        root.mauticPatchesInChipsScan = run;
        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        // The value input is replaced by an ajax answer whenever the operator, the field or the filter changes.
        $(document).ajaxComplete(run);
        // the Custom Objects condition replaces the value input of a row without an ajax call
        $(document).on('co:criterion-updated', run);
        $(run);
    }

    api.install = install;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesInChipsApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
