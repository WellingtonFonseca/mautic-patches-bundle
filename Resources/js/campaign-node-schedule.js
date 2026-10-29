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
 */
(function (root) {
    'use strict';

    var ORDER = [1, 2, 3, 4, 5, 6, 0];
    var CLOCK = 'ri-time-line';
    var CALENDAR = 'ri-calendar-line';
    var words = {};

    function configure(t) { words = t || {}; }

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
        (Array.isArray(list) ? list : []).forEach(function (day) {
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

    root.mauticPatchesScheduleApi = {configure: configure, format: format};

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.mauticPatchesScheduleApi; }
})(typeof window !== 'undefined' ? window : this);
