# Patches: schedule lines and date text of the wait strip

The wait strip on a campaign node (builder and Preview tab) has the wait text first, then one line per fact of the schedule (hours, days). The date of a "wait until a date" event is written in the user's language and time zone. Read this before touching the strip's lines or the date text. The strip itself (overlay, balloon removal, revalidate) is in wiki/patches-campaign-builder.md.

## What the strip shows

```json
{
  "strip": "`.campaign-event-wait` is a column of `.campaign-event-wait-line` rows (flex, icon + span). Row 1: `ri-hourglass-line` + `.campaign-event-wait-text` (core's text, 'Esperar 3 dias'). Under it ONE block `.campaign-event-wait-extra` (attribute data-lines = the signature of its rows) with a row per fact. No facts: no block. The strip is removed when there is no wait text.",
  "lines": "interval events only, in this order: fixed hour (`ri-time-line`, 'Às 9h30m'), window of hours (`ri-time-line`, 'Entre 10h e 16h20m'), days (`ri-calendar-line`, 'Seg, Qua, Qui'). A window needs BOTH ends (core's Interval scheduler ignores one alone). Hour format: 'on the hour' = '10h', else '16h20m' (no leading zero on the hour).",
  "days": "core's values: 0 Sunday ... 6 Saturday, -1 week days (= Monday to Friday, may come with other days). Shown Monday first. Three or more days in a row are a range ('Seg–Sex', Saturday and Sunday follow each other); the rest are listed. None or all seven = no line.",
  "date events": "a 'wait until a date' event has no lines (core keeps old hours in the event when the mode changes; they are ignored). Its text is core's, with the date swapped (below).",
  "words": "pt_BR and en_US keys `mautic.patches.campaign.schedule.*` in Translations/<locale>/messages.ini (hour, hour_minutes, at, window, range, day_0..day_6). CampaignNodeScheduleSubscriber gives them to the formatter with `configure(words, zone)`."
}
```

## One formatter, two sources

```json
{
  "formatter": "Resources/js/campaign-node-schedule.js (window.mauticPatchesScheduleApi: configure, format, localizeDate). Pure functions; inlined on every page by EventListener/CampaignNodeScheduleSubscriber (page.header.left). format(event) gives [{icon, text}].",
  "input": "an object with core's event fields: triggerMode, triggerHour, triggerRestrictedStartHour, triggerRestrictedStopHour, triggerRestrictedDaysOfWeek (and triggerDate). A time may be 'H:i', an ISO date or the {date: '1970-01-01 10:00:00.000000', timezone} object a DateTime has after json_encode.",
  "builder": "reads Mautic.campaignBuilderCanvasEvents[id] (core fills it from the page and after every edit). CampaignBuilderWaitLabelSubscriber's updateLabel() asks the formatter.",
  "preview": "CampaignPreviewWaitSubscriber::schedule(Event) leaves window.mauticPatchesPreviewSchedules (node id => the same fields; the date as ISO with its offset) next to the texts; window.mauticPatchesApplyWaits() asks the formatter. Sent even when empty, to replace the campaign seen before over ajax.",
  "edit in the builder": "core calls Mautic.campaignBuilderUpdateLabel BEFORE it stores the edited event in campaignBuilderCanvasEvents, so the lines would be one edit behind. patchEdit() wraps Mautic.campaignEventOnLoad (installed once, flag mauticPatchesSchedule, in patch()) and runs updateLabel again after core is done, only for an event that exists and was not deleted.",
  "revalidate": "setWait(node, label, lines) revalidates the node (jsPlumb) only when the wait text or the lines changed, all nodes of a moment on the next frame (as before)."
}
```

## Date text in the user's language and zone

```json
{
  "why": "core builds 'wait until %full%' with DateHelper::toFull: English month names (PHP date()) and the SYSTEM time zone only (default_timezone), whatever the user's settings.",
  "how": "localizeDate(label, event) finds core's two date templates (`mautic.campaign.connection.trigger.date.label` and `_inaction`, with %full%, given as words.date_plain and date_inaction) in core's text by its start and end, and swaps the date for Intl.DateTimeFormat(locale, {day, month long, year, hour numeric, minute 2-digit, timeZoneName short, timeZone}). Any language works because it matches the template, not a word. No match, no date, a bad date, or a locale or zone the browser rejects: core's text stays.",
  "zone": "CampaignNodeScheduleSubscriber::zone(): the user's locale and timezone, else the system's `locale` and `default_timezone` (core does the same at login in CoreSubscriber, _locale and _timezone). 'pt_BR' becomes 'pt-BR'; a zone PHP does not list becomes UTC; no locale becomes en-US. UserHelper::getUser(true): a guest has no user.",
  "instant": "instant(value) reads an ISO string with offset, 'Y-m-d H:i:s' (UTC), or the {date, timezone} object (timezone UTC, '+03:00' or a name such as America/Sao_Paulo; a name uses Intl to find the offset, twice, so the hour after a daylight saving change is right).",
  "no zone set": "when neither the user nor the system has a time zone the text ends in UTC ('às 17:00 UTC'): that is the configuration, not a bug. Set it in the user's profile or Settings > System."
}
```

## The capital of "Esperar"

```json
{
  "rule": "the pack's pt_BR texts 'esperar %number% %unit%' and 'esperar até %full%' are overridden to 'Esperar ...' in Resources/translation-overrides/pt_BR/messages.ini (see wiki/patches-forms-and-misc.md for how overrides load). The texts of the no path of a decision ('entre ...', 'por ...') stay lowercase.",
  "why it works everywhere": "the builder's plain() rewrite and the Preview's PHP both read the translator, which already has the override."
}
```

## Traps

- A plugin's own Translations/<locale> cannot replace a pack text: use Resources/translation-overrides.
- Playwright run by hand (without scripts/5/test-e2e.sh) has no MAUTIC_DB_CONTAINER: specs that run SQL fail with `docker exec  mysql`. Run specs through test-e2e.sh.
- In the event modal the days checkboxes are `_0` Monday ... `_5` Saturday, `_6` Sunday, `_7` week days. `_7` ignores a forced check (core's campaignEventSelectDOW unchecks the others): use `_5` in a spec.
- JSON_HEX_TAG and JSON_HEX_AMP write `<`, `>`, `&` as `<` and so on, and json_encode writes `/` as `\/`: a PHPUnit expectation must build the string with chr(92).
- The fake DOM of Tests/js/campaign-wait-label.test.js builds the first line itself: it does not read the strip's HTML, so the hourglass class is checked by the PHPUnit string test and the browser spec.
- A Node test of a zone must not rely on the machine zone: 'a date with no zone is UTC' sets process.env.TZ to Asia/Tokyo and restores it.
- A line text that has the date in it ('às 17:00') comes from Intl, so the 'às' there is lowercase; the capital 'Às' is the fixed-hour line.

## Tests

- Tests/js/campaign-node-schedule.test.js (formatter, dates, zones, daylight saving time, midnight, templates), Tests/js/campaign-wait-label.test.js (strip with lines, edit hook, Preview).
- Tests/Unit/EventListener/CampaignNodeScheduleSubscriberTest, CampaignPreviewWaitSubscriberTest, CampaignBuilderWaitLabelSubscriberTest (strings of the script and the style).
- scripts/5/e2e/tests/campaign-wait-schedule.spec.js (lines, strip height, both themes, edit in the builder), campaign-preview-wait.spec.js (date in Portuguese, same text in builder and Preview, edit of the date).
- Infection leaves only equivalent mutants: `isInactionWait` public, and the date branch of CampaignPreviewWaitSubscriber::label (final DateHelper, the browser spec checks it).
