// The formatter of the schedule lines (Resources/js/campaign-node-schedule.js): the hours and the days of an interval event as
// [{icon, text}] for the strip of its node.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/campaign-node-schedule.js');

const WORDS = {
    hour: '%hour%h', hour_minutes: '%hour%h%minutes%m', at: 'Às %time%', window: 'Entre %start% e %end%', range: '%from%–%to%',
    day_0: 'Dom', day_1: 'Seg', day_2: 'Ter', day_3: 'Qua', day_4: 'Qui', day_5: 'Sex', day_6: 'Sáb',
    date_plain: 'esperar até %full%', date_inaction: 'por %full%',
};
const UTC = {locale: 'pt-BR', timeZone: 'UTC'};
const CLOCK = 'ri-time-line';
const CALENDAR = 'ri-calendar-line';

const lines = (fields) => api.format({triggerMode: 'interval', ...fields});
const texts = (fields) => lines(fields).map((l) => l.text);

test.beforeEach(() => api.configure(WORDS, UTC));

test('a window of hours: "Entre 10h e 16h20m", with a clock', () => {
    assert.deepStrictEqual(lines({triggerRestrictedStartHour: '10:00', triggerRestrictedStopHour: '16:20'}), [{icon: CLOCK, text: 'Entre 10h e 16h20m'}]);
    assert.deepStrictEqual(texts({triggerRestrictedStartHour: '08:30', triggerRestrictedStopHour: '17:45'}), ['Entre 8h30m e 17h45m']);
});

test('a fixed hour: "Às 9h30m"', () => {
    assert.deepStrictEqual(lines({triggerHour: '09:30'}), [{icon: CLOCK, text: 'Às 9h30m'}]);
    assert.deepStrictEqual(texts({triggerHour: '14:00'}), ['Às 14h']);
    assert.deepStrictEqual(texts({triggerHour: '00:05'}), ['Às 0h05m']);
});

test('a time may be "H:i", the object a DateTime has after json_encode, or an ISO date', () => {
    assert.deepStrictEqual(texts({triggerHour: {date: '1970-01-01 09:30:00.000000', timezone_type: 3, timezone: 'UTC'}}), ['Às 9h30m']);
    assert.deepStrictEqual(texts({triggerHour: '2026-10-29T09:30:00+00:00'}), ['Às 9h30m']);
    assert.deepStrictEqual(texts({triggerHour: '9:05'}), ['Às 9h05m']);
});

test('a window needs both ends: one alone shows nothing', () => {
    assert.deepStrictEqual(lines({triggerRestrictedStartHour: '10:00'}), []);
    assert.deepStrictEqual(lines({triggerRestrictedStopHour: '16:00'}), []);
});

test('days: a calendar line; three or more in a row are a range, the rest are listed, Monday first', () => {
    assert.deepStrictEqual(lines({triggerRestrictedDaysOfWeek: [1, 3, 4]}), [{icon: CALENDAR, text: 'Seg, Qua, Qui'}]);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [1, 2, 3, 4, 5]}), ['Seg–Sex']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [1, 2, 3]}), ['Seg–Qua']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [1, 2]}), ['Seg, Ter']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [5, 3, 1]}), ['Seg, Qua, Sex']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [1, 2, 3, 5]}), ['Seg–Qua, Sex']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [3]}), ['Qua']);
});

test('Sunday is the last day of the week, so Saturday and Sunday follow each other', () => {
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [0, 6]}), ['Sáb, Dom']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [0, 5, 6]}), ['Sex–Dom']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [0]}), ['Dom']);
});

test('-1 is the week days (Monday to Friday), alone or with other days; repeats count once', () => {
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [-1]}), ['Seg–Sex']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [-1, 6]}), ['Seg–Sáb']);
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [-1, 1, '2']}), ['Seg–Sex']);
});

test('all seven days, or none, is no restriction', () => {
    assert.deepStrictEqual(lines({triggerRestrictedDaysOfWeek: [0, 1, 2, 3, 4, 5, 6]}), []);
    assert.deepStrictEqual(lines({triggerRestrictedDaysOfWeek: []}), []);
    assert.deepStrictEqual(lines({triggerRestrictedDaysOfWeek: null}), []);
    assert.deepStrictEqual(lines({}), []);
});

test('values that are not a day are ignored', () => {
    assert.deepStrictEqual(texts({triggerRestrictedDaysOfWeek: [7, -2, 'x', 3]}), ['Qua']);
    assert.deepStrictEqual(lines({triggerRestrictedDaysOfWeek: [7, 9]}), []);
});

test('hour, window and days together: three lines, in that order', () => {
    assert.deepStrictEqual(
        lines({triggerHour: '09:30', triggerRestrictedStartHour: '10:00', triggerRestrictedStopHour: '16:20', triggerRestrictedDaysOfWeek: [1, 3, 4]}),
        [{icon: CLOCK, text: 'Às 9h30m'}, {icon: CLOCK, text: 'Entre 10h e 16h20m'}, {icon: CALENDAR, text: 'Seg, Qua, Qui'}],
    );
});

test('only an interval wait has a schedule: immediate, date and a missing event have none', () => {
    const fields = {triggerHour: '09:30', triggerRestrictedDaysOfWeek: [1]};
    assert.deepStrictEqual(api.format({triggerMode: 'immediate', ...fields}), []);
    assert.deepStrictEqual(api.format({triggerMode: 'date', ...fields}), []);
    assert.deepStrictEqual(api.format({...fields}), []);
    assert.deepStrictEqual(api.format(null), []);
    assert.deepStrictEqual(api.format(undefined), []);
});

test('the words of another language are used, and a placeholder the language lacks is left as it is', () => {
    api.configure({...WORDS, at: 'at %time%', hour: '%hour%:00', hour_minutes: '%hour%:%minutes%', window: '%start% to %end% %zone%'});

    assert.deepStrictEqual(texts({triggerHour: '09:30'}), ['at 9:30']);
    assert.deepStrictEqual(texts({triggerRestrictedStartHour: '10:00', triggerRestrictedStopHour: '16:20'}), ['10:00 to 16:20 %zone%']);
});

// ---- the date of a wait until a date, in the language and the time zone of the user

const EN = 'esperar até October 31, 2026 5:00 pm UTC';
const dated = (fields) => ({triggerMode: 'date', ...fields});
const OCT31 = {date: '2026-10-31 17:00:00.000000', timezone_type: 3, timezone: 'UTC'};

test('the date is written in the language of the user, in UTC when that is the zone', () => {
    assert.match(api.localizeDate(EN, dated({triggerDate: OCT31})), /^esperar até 31 de outubro de 2026 às 17:00 UTC$/);
});

test('the date is written in the time zone of the user: 17:00 UTC is 14:00 in São Paulo', () => {
    api.configure(WORDS, {locale: 'pt-BR', timeZone: 'America/Sao_Paulo'});

    assert.match(api.localizeDate(EN, dated({triggerDate: OCT31})), /^esperar até 31 de outubro de 2026 às 14:00 /);
});

test('another language: English month names and a 12-hour clock', () => {
    api.configure({...WORDS, date_plain: 'wait until %full%'}, {locale: 'en-US', timeZone: 'UTC'});

    assert.match(api.localizeDate('wait until October 31, 2026 5:00 pm UTC', dated({triggerDate: OCT31})), /^wait until October 31, 2026 at 5:00 PM UTC$/);
});

test('the no path of a decision keeps its own template ("por ...")', () => {
    assert.match(api.localizeDate('por October 31, 2026 5:00 pm UTC', dated({triggerDate: OCT31})), /^por 31 de outubro de 2026 às 17:00 UTC$/);
});

test('a date may come as an ISO string with its offset, as "Y-m-d H:i:s" (UTC), or written in another zone', () => {
    const at = (triggerDate) => api.localizeDate(EN, dated({triggerDate}));

    assert.match(at('2026-10-31T17:00:00+00:00'), /às 17:00 UTC$/);
    assert.match(at('2026-10-31T14:00:00-03:00'), /às 17:00 UTC$/);
    assert.match(at('2026-10-31T17:00:00Z'), /às 17:00 UTC$/);
    assert.match(at('2026-10-31 17:00:00'), /às 17:00 UTC$/);
    assert.match(at({date: '2026-10-31 14:00:00.000000', timezone_type: 3, timezone: 'America/Sao_Paulo'}), /às 17:00 UTC$/);
    assert.match(at({date: '2026-10-31 20:00:00.000000', timezone_type: 1, timezone: '+03:00'}), /às 17:00 UTC$/);
    assert.match(at({date: '2026-10-31 12:00:00.000000', timezone_type: 1, timezone: '-0500'}), /às 17:00 UTC$/);
    assert.match(at({date: '2026-10-31 17:00:00.000000'}), /às 17:00 UTC$/, 'no zone given: UTC');
});

test('a text that is not core\'s date text, or an event without a date, is left as it is', () => {
    assert.strictEqual(api.localizeDate('esperar 2 dias', dated({triggerDate: OCT31})), 'esperar 2 dias');
    assert.strictEqual(api.localizeDate(EN, null), EN);
    assert.strictEqual(api.localizeDate(EN, {triggerMode: 'interval', triggerDate: OCT31}), EN);
    assert.strictEqual(api.localizeDate(EN, dated({})), EN);
    assert.strictEqual(api.localizeDate(EN, dated({triggerDate: 'not a date'})), EN);
    assert.strictEqual(api.localizeDate('', dated({triggerDate: OCT31})), '');
});

test('a locale or a zone the browser does not know leaves core\'s text as it is', () => {
    api.configure(WORDS, {locale: 'pt-BR', timeZone: 'Nowhere/Land'});
    assert.strictEqual(api.localizeDate(EN, dated({triggerDate: OCT31})), EN);

    api.configure(WORDS, {locale: 'not a locale', timeZone: 'UTC'});
    assert.strictEqual(api.localizeDate(EN, dated({triggerDate: OCT31})), EN);
});

test('a template that has text after the date keeps it, and one without %full% never matches', () => {
    api.configure({...WORDS, date_plain: 'esperar até %full% (horário do usuário)', date_inaction: 'sem data aqui'}, UTC);

    assert.match(api.localizeDate('esperar até October 31, 2026 5:00 pm UTC (horário do usuário)', dated({triggerDate: OCT31})), /^esperar até 31 de outubro de 2026 às 17:00 UTC \(horário do usuário\)$/);
    assert.strictEqual(api.localizeDate('sem data aqui', dated({triggerDate: OCT31})), 'sem data aqui');
    assert.strictEqual(api.localizeDate('esperar até October 31, 2026 5:00 pm UTC', dated({triggerDate: OCT31})), 'esperar até October 31, 2026 5:00 pm UTC', 'the end of the text must match the template');
});

test('without configure() the date is written in English, UTC', () => {
    api.configure(null, null);

    assert.strictEqual(api.localizeDate(EN, dated({triggerDate: OCT31})), EN, 'no template known: core\'s text stays');
    api.configure({date_plain: 'wait until %full%'});
    assert.match(api.localizeDate('wait until x', dated({triggerDate: OCT31})), /^wait until October 31, 2026 at 5:00 PM UTC$/);
});

test('a date written in a zone with daylight saving time lands on the right instant, also right after the change', () => {
    const at = (date, timeZone) => api.localizeDate(EN, dated({triggerDate: {date, timezone_type: 3, timezone: timeZone}}));

    // New York 2026-03-08 03:30 is EDT (UTC-4) because the clocks moved forward at 02:00: 07:30 UTC
    assert.match(at('2026-03-08 03:30:00.000000', 'America/New_York'), /às 07:30 UTC$/);
    // the day before it is EST (UTC-5)
    assert.match(at('2026-03-07 03:30:00.000000', 'America/New_York'), /às 08:30 UTC$/);
});

test('midnight and noon in a named zone are not mixed up with a 12-hour clock', () => {
    const at = (date) => api.localizeDate(EN, dated({triggerDate: {date, timezone_type: 3, timezone: 'America/Sao_Paulo'}}));

    assert.match(at('2026-10-31 00:30:00.000000'), /às 03:30 UTC$/);
    assert.match(at('2026-10-31 12:30:00.000000'), /às 15:30 UTC$/);
});

test('the month and the day of a named zone are read as written (a month is not shifted by one)', () => {
    assert.match(api.localizeDate(EN, dated({triggerDate: {date: '2026-01-15 12:00:00.000000', timezone_type: 3, timezone: 'Europe/Lisbon'}})), /^esperar até 15 de janeiro de 2026 às 12:00 UTC$/);
});

test('a date with no zone is UTC whatever the zone of the browser is', () => {
    const before = process.env.TZ;
    process.env.TZ = 'Asia/Tokyo';
    try {
        assert.match(api.localizeDate(EN, dated({triggerDate: {date: '2026-10-31 17:00:00.000000'}})), /às 17:00 UTC$/);
        assert.match(api.localizeDate(EN, dated({triggerDate: '2026-10-31 17:00:00'})), /às 17:00 UTC$/);
    } finally {
        if (before === undefined) { delete process.env.TZ; } else { process.env.TZ = before; }
    }
});

test('the start and the end of a template must not overlap: "aba" is not "ab %full% ba"', () => {
    api.configure({...WORDS, date_plain: 'ab%full%ba', date_inaction: 'x %full% y'}, UTC);

    assert.strictEqual(api.localizeDate('aba', dated({triggerDate: OCT31})), 'aba');
    assert.match(api.localizeDate('abOctober 31, 2026 5:00 pm UTCba', dated({triggerDate: OCT31})), /^ab31 de outubro de 2026 às 17:00 UTCba$/);
});
