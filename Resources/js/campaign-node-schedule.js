/*
 * The schedule of a campaign event (the hour, the window of hours and the days of the week of an "interval" wait) as the lines that
 * go under the wait text in the strip of its node, so nobody opens the node to know when it sends.
 *
 * One formatter for the builder and the Preview tab: both give it an object with the fields of core's event (triggerMode,
 * triggerHour, triggerRestrictedStartHour, triggerRestrictedStopHour, triggerRestrictedDaysOfWeek). A time may be "H:i", an ISO date
 * or the {date: "1970-01-01 10:00:00.000000"} object that a DateTime has after json_encode. The words come from the translator
 * (CampaignNodeScheduleSubscriber calls configure() with them).
 *
 * Days of the week follow core's form: 0 Sunday ... 6 Saturday, -1 "week days" (Monday to Friday). Shown Monday first.
 *
 * It also writes the date of a "wait until a date" event in the language and the time zone of the user (localizeDate): core's text
 * ("wait until October 31, 2026 5:00 pm UTC") has English month names and the system time zone, whatever the user's settings.
 */
(function (root) {
    'use strict';

    var ORDER = [1, 2, 3, 4, 5, 6, 0];
    var CLOCK = 'ri-time-line';
    var CALENDAR = 'ri-calendar-line';
    var words = {};
    var zone = {locale: 'en-US', timeZone: 'UTC'};
    var dateFormat = null; // the Intl formatter of the user's locale and zone, made once (a formatter is costly to build)

    // the words of the language, and the locale ("pt-BR") and IANA time zone ("America/Sao_Paulo") of the user
    function configure(t, z) {
        words = t || {};
        zone = {locale: (z && z.locale) || 'en-US', timeZone: (z && z.timeZone) || 'UTC'};
        dateFormat = null;
    }

    function fill(text, values) {
        return String(text || '').replace(/%(\w+)%/g, function (all, name) { return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : all; });
    }

    // [hours, minutes] (hours without a leading zero, minutes with two digits), or null
    function time(value) {
        if (value === null || value === undefined || value === '') { return null; }
        var text = typeof value === 'object' ? value.date : String(value);
        var match = /(\d{1,2}):(\d{2})/.exec(text || '');

        return match ? [String(Number(match[1])), match[2]] : null;
    }

    // "10h" on the hour, "16h20m" otherwise (the words say how: pt_BR "%hour%h%minutes%m", en_US "%hour%:%minutes%")
    function clock(parts) {
        return fill('00' === parts[1] ? words.hour : words.hour_minutes, {hour: parts[0], minutes: parts[1]});
    }

    // the days chosen, Monday first, without repeats; null when there is no restriction (none chosen, or all seven)
    function days(list) {
        var chosen = {};
        // PHP writes an array with gaps in its keys as an object ({"0": 1, "2": 3}): its values are the days
        var given = Array.isArray(list) ? list : (list && typeof list === 'object' ? Object.keys(list).map(function (k) { return list[k]; }) : []);
        given.forEach(function (day) {
            day = Number(day);
            if (day === -1) { [1, 2, 3, 4, 5].forEach(function (d) { chosen[d] = true; }); } else if (day >= 0 && day <= 6) { chosen[day] = true; }
        });
        var picked = ORDER.filter(function (day) { return chosen[day]; });

        return picked.length === 0 || picked.length === 7 ? null : picked;
    }

    // runs of three or more consecutive days become "Seg–Qua"; the rest are listed ("Seg, Qua, Sex")
    function short(picked) {
        var runs = [];
        picked.forEach(function (day) {
            var last = runs[runs.length - 1];
            if (last && ORDER.indexOf(day) === ORDER.indexOf(last[last.length - 1]) + 1) { last.push(day); } else { runs.push([day]); }
        });
        var out = [];
        runs.forEach(function (run) {
            if (run.length >= 3) {
                out.push(fill(words.range, {from: words['day_' + run[0]], to: words['day_' + run[run.length - 1]]}));
            } else {
                run.forEach(function (day) { out.push(words['day_' + day]); });
            }
        });

        return out.join(', ');
    }

    // how far (ms) the wall clock of a zone is ahead of UTC at an instant
    function offsetAt(ms, timeZone) {
        var v = {};
        new Intl.DateTimeFormat('en-US', {timeZone: timeZone, hourCycle: 'h23', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric'})
            .formatToParts(new Date(ms)).forEach(function (p) { v[p.type] = Number(p.value); });

        return Date.UTC(v.year, v.month - 1, v.day, v.hour, v.minute, v.second) - Math.floor(ms / 1000) * 1000;
    }

    // the offset (ms) of the zone a date was written in: UTC, "+03:00" or a name ("America/Sao_Paulo") at that wall time
    function zoneOffset(name, wallMs) {
        if (!name || name === 'UTC' || name === 'Z') { return 0; }
        var fixed = /^([+-])(\d{2}):?(\d{2})$/.exec(name);
        if (fixed) { return (fixed[1] === '-' ? -1 : 1) * (Number(fixed[2]) * 60 + Number(fixed[3])) * 60000; }
        try {
            var first = offsetAt(wallMs, name);

            return offsetAt(wallMs - first, name);
        } catch (e) {
            return 0;
        }
    }

    // the Date of a core date: an ISO string with its offset, "Y-m-d H:i:s" (UTC), or the {date, timezone} object a DateTime has after json_encode
    function instant(value) {
        if (!value) { return null; }
        var text = typeof value === 'object' ? value.date : String(value);
        if (/T.*(?:Z|[+-]\d{2}:?\d{2})$/.test(text || '')) {
            var iso = new Date(text);

            return isNaN(iso.getTime()) ? null : iso;
        }
        var m = /(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(text || '');
        if (!m) { return null; }
        var wall = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), Number(m[6] || 0));

        return new Date(wall - zoneOffset(typeof value === 'object' ? value.timezone : 'UTC', wall));
    }

    // "31 de outubro de 2026 às 14:00 BRT" (or "GMT-3", the browser decides) in the language and zone of the user; null if the browser cannot
    function dateText(when) {
        try {
            dateFormat = dateFormat || new Intl.DateTimeFormat(zone.locale, {timeZone: zone.timeZone, day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short'});

            return dateFormat.format(when);
        } catch (e) {
            return null;
        }
    }

    // core's text of a "wait until a date" event with its English date replaced by ours. The text is matched against the two templates
    // of the language ("esperar até %full%", the no path's "por %full%"), so any language works. Anything else is returned as it is.
    function localizeDate(label, event) {
        if (!label || !event || event.triggerMode !== 'date') { return label; }
        var when = instant(event.triggerDate);
        var full = when ? dateText(when) : null;
        if (!full) { return label; }

        var templates = [words.date_plain, words.date_inaction];
        for (var i = 0; i < templates.length; i++) {
            var parts = String(templates[i] || '').split('%full%');
            if (parts.length !== 2) { continue; }
            var head = parts[0];
            var tail = parts[1];
            if (label.length >= head.length + tail.length && label.indexOf(head) === 0 && label.slice(label.length - tail.length) === tail) { return head + full + tail; }
        }

        return label;
    }

    // the lines for the strip, [{icon, text}]: the fixed hour, the window of hours, the days. Empty when there is nothing to show.
    function format(event) {
        var lines = [];
        if (!event || event.triggerMode !== 'interval') { return lines; }

        var hour = time(event.triggerHour);
        var start = time(event.triggerRestrictedStartHour);
        var stop = time(event.triggerRestrictedStopHour);
        var picked = days(event.triggerRestrictedDaysOfWeek);

        if (hour) { lines.push({icon: CLOCK, text: fill(words.at, {time: clock(hour)})}); }
        if (start && stop) { lines.push({icon: CLOCK, text: fill(words.window, {start: clock(start), end: clock(stop)})}); }
        if (picked) { lines.push({icon: CALENDAR, text: short(picked)}); }

        return lines;
    }

    root.mauticPatchesScheduleApi = {configure: configure, format: format, localizeDate: localizeDate};

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.mauticPatchesScheduleApi; }
})(typeof window !== 'undefined' ? window : this);
