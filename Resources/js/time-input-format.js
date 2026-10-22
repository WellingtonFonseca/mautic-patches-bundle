/*
 * Formatter for every date / time field that uses core's datetimepicker (the hour of a campaign event, the dates of the
 * segment filters, email, dynamic content, reports...). The picker's own `mask` option garbles what is typed (the cursor
 * jumps), so the field is formatted here, from the picker's own format: typing 1430 in an `H:i` field gives 14:30, and
 * 202610211430 in `Y-m-d H:i` gives 2026-10-21 14:30. Only digits are kept; the separators are put by the script.
 *
 * Each slot is held to its range (hour 00-23, minute and second 00-59, month 01-12, day 01-31): a first digit that
 * could not start a valid slot (an hour of 3...9) gets a 0 before it, and a complete slot out of range is lowered to the
 * limit. When the field loses focus, a partial time is completed (9 gives 09:00, 14:3 gives 14:03); a partial date is left as it is.
 *
 * The date filters of the segment form also take RELATIVE values typed by hand. Core keeps a filter relative only when it is one of
 * its own keywords (today, this week...) translated into the language of the user, or when it starts with a sign (`-1 days`,
 * `+2 weeks`); any other text it turns into a fixed date (a field of date and time) or ignores. English words work in core only in
 * a field of date and only today, tomorrow and yesterday, so they are accepted here and, when the field loses focus, swapped for the
 * word of the user's language. The page gives the map in window.mauticPatchesRelativeDates: every word it accepts (the language of
 * the user and English, in lower case) to the word core keeps relative. A text that starts with a letter or a sign is left as typed
 * while it is typed (no digits-only rule) and, when it is committed (leaving the field, Enter), checked as a whole, only if a person typed it (a stored value that was already there is never touched): a valid value stays (swapped when it
 * is an English word), any other is cleared. A text that starts with a digit is a date and is formatted as above. Time fields and
 * the dates of other forms keep the digits-only rule.
 *
 * It listens on the document, so fields that Mautic loads over ajax (modals) are covered. A field is formatted only
 * once the picker started on it or core activated it (calendar-activated); a format with a token that is not
 * Y, m, d, H, i or s (12 hour clock, month names) is left alone.
 */
(function (root) {
    'use strict';

    var SLOTS = {Y: {len: 4}, m: {len: 2, max: 12, min: 1}, d: {len: 2, max: 31, min: 1}, H: {len: 2, max: 23, min: 0}, i: {len: 2, max: 59, min: 0}, s: {len: 2, max: 59, min: 0}};
    var TIME  = {H: true, i: true, s: true};

    /** The format as a list of {slot} and {text}; null when it has a token this script does not know. */
    function tokens(format) {
        if (typeof format !== 'string' || !format) { return null; }
        var list = [], hasSlot = false;
        for (var k = 0; k < format.length; k++) {
            var c = format.charAt(k);
            if (SLOTS[c]) { list.push({slot: c}); hasSlot = true; }
            else if (/[A-Za-z]/.test(c)) { return null; }
            else { list.push({text: c}); }
        }

        return hasSlot ? list : null;
    }

    function pad(n, len) { var s = String(n); while (s.length < len) { s = '0' + s; } return s; }

    /** One slot from the digits it got: {part, used}. A first digit that cannot start a valid slot gets a 0, a full slot is held to its range. */
    function fit(slot, digits) {
        var def = SLOTS[slot];
        if (def.max !== undefined && Number(digits.charAt(0)) * 10 > def.max) { return {part: '0' + digits.charAt(0), used: 1}; }
        var part = digits.slice(0, def.len);
        if (def.max !== undefined && part.length === 2) {
            var n = Number(part);
            if (n > def.max) { part = pad(def.max, 2); }
            if (n < def.min) { part = pad(def.min, 2); }
        }

        return {part: part, used: Math.min(digits.length, def.len)};
    }

    /** Typed text (any characters) to the text for the format. `deleting`: no separator after a full slot, so Backspace can clear it. */
    function format(value, fmt, deleting) {
        var list = tokens(fmt);
        if (!list) { return value; }
        var digits = String(value === null || value === undefined ? '' : value).replace(/\D/g, '');
        var out = '', pending = '';
        for (var k = 0; k < list.length; k++) {
            var t = list[k];
            if (t.text !== undefined) { pending += t.text; continue; }
            if (!digits.length) {
                // the separator after a full slot shows at once, unless the user is deleting
                if (!deleting && out) { out += pending; }
                break;
            }
            var got = fit(t.slot, digits);
            digits = digits.slice(got.used);
            out += pending + got.part;
            pending = '';
            if (got.part.length < SLOTS[t.slot].len) { break; }
        }

        return out;
    }

    /** On blur: a partial time is completed (9 -> 09:00, 14:3 -> 14:03). Dates and empty values are returned as they are. */
    function complete(value, fmt) {
        var list = tokens(fmt);
        value = String(value === null || value === undefined ? '' : value);
        if (!list || !value.replace(/\D/g, '').length) { return value; }
        var digits = value.replace(/\D/g, ''), out = '', k;
        for (k = 0; k < list.length; k++) {
            var t = list[k];
            if (t.text !== undefined) { out += t.text; continue; }
            var def = SLOTS[t.slot];
            var part = digits.slice(0, def.len);
            digits = digits.slice(def.len);
            if (part.length === def.len) { out += part; continue; }
            if (!TIME[t.slot]) { return value; }
            // a partial time slot: 1 digit is the unit ("3" -> "03"), none is "00"
            out += part.length ? fit(t.slot, pad(part, def.len)).part : pad(0, def.len);
        }

        return out;
    }

    /** A text that starts with a letter or a sign is a relative value being typed, not a date. */
    function isFreeText(text) {
        return /^[A-Za-z\u00C0-\u024F+-]/.test(String(text === null || text === undefined ? '' : text));
    }

    // one or more terms (`-1 days`, `-1 week +2 days`): core keeps anything that starts with a sign and reads it as an interval
    var SIGNED = /^([+-]\d+\s?(second|minute|hour|day|week|month|year)s?\s*)+$/i;

    function has(map, key) { return !!map && Object.prototype.hasOwnProperty.call(map, key); }

    /** What the field keeps when it loses focus: the word core keeps relative for a word of the page, a signed offset as it is, else ''. */
    function relativeValue(text, map) {
        text = String(text === null || text === undefined ? '' : text).replace(/^\s+|\s+$/g, '');
        if (has(map, text.toLowerCase())) { return map[text.toLowerCase()]; }

        return SIGNED.test(text) ? text : '';
    }

    /** Marks a free text field as typed in by a person: only that is checked when it is committed, never a value that was already there. */
    var TOUCHED = 'data-patches-relative-typed';

    /** Relative values are typed in the date filters of the segment form only, in a field whose format has a date part. */
    function relativeAllowed(el, fmt) {
        return !!(el.closest && el.closest('.segment-filter') && /[Ymd]/.test(fmt));
    }

    var TOGGLE_FORMAT = {time: 'H:i', date: 'Y-m-d', datetime: 'Y-m-d H:i'};

    /**
     * The format of a field the picker works on, or null: the one in the options of the picker's popup (it is there once the
     * picker started), else the data-format / data-toggle that core's activateDateTimeInputs reads (the field then has the
     * class calendar-activated).
     */
    function formatOf($, el) {
        var popup = $(el).data('xdsoft_datetimepicker');
        var options = popup && popup.data && popup.data('options');
        if (options && options.format) { return options.format; }
        if (!el.classList || !el.classList.contains('calendar-activated')) { return null; }

        return el.getAttribute('data-format') || TOGGLE_FORMAT[el.getAttribute('data-toggle')] || null;
    }

    function install($, doc) {
        if (!$ || !doc || doc.mauticPatchesTimeInputFormat) { return; }
        doc.mauticPatchesTimeInputFormat = true;

        doc.addEventListener('input', function (e) {
            var el = e.target;
            if (!el || el.tagName !== 'INPUT') { return; }
            // typed by a person: marked at once, before the format is known (the picker starts ~100 ms after the focus, and a fast
            // typist is done by then); the check itself is made when the value is committed (the change event, below)
            if (el.closest && el.closest('.segment-filter') && isFreeText(el.value)) { el.setAttribute(TOUCHED, '1'); }
            var fmt = formatOf($, el);
            if (!fmt || !tokens(fmt)) { return; }
            // a relative value is typed as it is, with no digits-only rule
            if (relativeAllowed(el, fmt) && isFreeText(el.value)) { return; }
            var deleting = /^delete/.test(e.inputType || '');
            var before = el.value;
            var after = format(before, fmt, deleting);
            if (after === before) { return; }
            // keep the caret after the same number of digits when the edit was not at the end
            var digitsBefore = before.slice(0, el.selectionStart === null ? before.length : el.selectionStart).replace(/\D/g, '').length;
            var atEnd = el.selectionStart === null || el.selectionStart >= before.length;
            el.value = after;
            if (!atEnd && el.setSelectionRange) {
                var pos = 0, seen = 0;
                while (pos < after.length && seen < digitsBefore) { if (/\d/.test(after.charAt(pos))) { seen++; } pos++; }
                el.setSelectionRange(pos, pos);
            }
        }, true);

        doc.addEventListener('focusout', function (e) {
            var el = e.target;
            if (!el || el.tagName !== 'INPUT') { return; }
            var fmt = formatOf($, el);
            if (!fmt || !tokens(fmt)) { return; }
            // a relative value is not a time to complete: it is checked when it is committed (the change event, below)
            if (relativeAllowed(el, fmt) && isFreeText(el.value)) { return; }
            var done = complete(el.value, fmt);
            if (done !== el.value) {
                el.value = done;
                $(el).trigger('change');
            }
        }, true);

        // The commit of a value (leaving the field, or Enter, or a script): a relative value that a person typed is checked as a whole,
        // a valid one stays (an English word is swapped for the word core keeps relative), any other is cleared. Runs before the other
        // listeners (capture), which then see the final value.
        doc.addEventListener('change', function (e) {
            var el = e.target;
            if (!el || el.tagName !== 'INPUT' || !el.hasAttribute(TOUCHED)) { return; }
            el.removeAttribute(TOUCHED);
            var fmt = formatOf($, el);
            if (!fmt || !tokens(fmt) || !relativeAllowed(el, fmt) || !isFreeText(el.value)) { return; }
            el.value = relativeValue(el.value, root.mauticPatchesRelativeDates);
        }, true);
    }

    var api = {install: install, tokens: tokens, format: format, complete: complete, formatOf: formatOf, isFreeText: isFreeText, relativeValue: relativeValue};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesTimeInputFormatApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
