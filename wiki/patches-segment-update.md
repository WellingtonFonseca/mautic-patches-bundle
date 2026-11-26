# Patches: segment rebuild API and the Update buttons

`POST /api/segments/{id}/rebuild` (async, runs the real segments command), the Update button and its lock until the rebuild is done on the segment list and page, and the same on the campaign.

## What it does

```json
{
  "segment rebuild API": "POST /api/segments/{id}/rebuild, see the section Segment rebuild API of this file"
}
```

## Segment rebuild API

Mautic's REST API can add or remove contacts of a segment by hand, but it has no call to recalculate a dynamic segment from its filters. That only exists as the command `mautic:segments:update --list-id=N`, normally run by the cron. Checked in the core (`LeadBundle/Command/UpdateLeadListsCommand.php`, `Model/ListModel::rebuildListLeads()`).

```json
{
  "call": "POST /api/segments/{id}/rebuild (same Basic Auth as every /api route, no body)",
  "answer": "202 {\"segment\": {\"id\": 7, \"name\": \"...\"}, \"status\": \"dispatched\"} as soon as the rebuild was started. It does NOT wait for it.",
  "errors": "404 no such segment; 403 the user cannot edit the segment (lead:lists:editown/editother); 409 segment not published (the command skips those); 500 the background process could not be started; 401 no auth",
  "how it runs": "Service/SegmentRebuildLauncher starts `nohup php bin/console mautic:segments:update --list-id=N >/dev/null 2>&1 &`. Detached because Symfony's Process kills its child when the request ends.",
  "why the command and not ListModel::rebuildListLeads() in the controller": "the command already holds a lock per segment id (a second run for the same segment exits with 'Script in progress'), does the batching and saves last built date/time. Calling the model directly would skip the lock and could overlap with the cron. The launcher never passes --bypass-locking or --force.",
  "progress": "there is no status endpoint. Poll GET /api/segments/{id} and watch the last built date, or the segment's contact count.",
  "php binary": "PhpExecutableFinder, because PHP_BINARY is the web server's binary under mod_php",
  "service wiring": "SegmentRebuildLauncher has a string constructor argument (project dir), so it is declared in Config/services.php. A class with such an argument in an autowired folder breaks cache:clear."
}
```

## "Update" in the segment list

Each row of the segment list has a three-dots menu (Edit, Clone, Delete, View contacts). The core renders it with `Helper/list_actions.html.twig`, which asks `VIEW_INJECT_CUSTOM_BUTTONS` for extra buttons at location `list_actions`. `EventListener/SegmentListButtonSubscriber` adds **Update** (pt_BR "Atualizar") there, for route `mautic_segment_index`, only when `SegmentRebuilder::hasEditAccess()` for the row menu (see `row menu and the status toggle` below) and `canRebuild()` (edit access + published) on the page. No core template was edited.

```json
{
  "click": "ajax POST (data-toggle=ajax, data-method=POST) to /s/segment-rebuild/{id}, route mautic_patches_segment_rebuild, Controller/SegmentRebuildController. Session login, not API auth. Answers with a flash and goes back to the segment list.",
  "csrf": "Mautic's own JS sends the page's ajax token (X-CSRF-Token) on every ajax call, so the route is protected for free. Without it nothing happens (tested).",
  "why not /segments/rebuild/{id}": "core's /s/segments/{objectAction}/{objectId} would match first",
  "one implementation": "Service/SegmentRebuilder (existence, editown/editother, published, launch) is used by the API and by this button; DTO/SegmentRebuildResult carries the outcome. API maps it to 404/403/409/500/202, the button to flash messages (Translations/*/flashes.ini)."
}
```

```json
{
  "row menu and the status toggle": "the row menu is built by the server once; activating or deactivating from it (core's icon or the plugin's Ativar/Desativar item) swaps only the label/icon, never the menu. So the list's Update item is rendered for every row whose user may edit (hasEditAccess), with the `hidden` attribute while the segment/campaign is inactive, and list-status-toggle.js toggleUpdate(scope, path, id, status) removes or sets `hidden` as the status changes (segment: apply(); campaign: syncCampaigns(), not while core's icon spins). CSS in SegmentStatusSubscriber::STYLE / CampaignStatusSubscriber::STYLE hides the `li` of a hidden item (`li:has(>a[hidden][href*=\"/s/segment-rebuild/\"])`). The detail page keeps the published-only rule (it has no toggle).",
  "tests": "PHPUnit (the hidden attribute, priority, hasEditAccess), Tests/js/list-status-toggle.test.js (toggleUpdate), browser specs segment-status.spec.js and campaign-update.spec.js (activate shows it, deactivate hides it, no reload)."
}
```

**Also on the segment's own page**: the page (`LeadBundle/Resources/views/List/details.html.twig`) renders its Edit button and the dropdown with Clone/Delete through `page_actions.html.twig`, which asks the same event for buttons at location `page_actions`. The subscriber adds Update there only for route `mautic_segment_action` with `objectAction=view` (not edit, clone or new). The link carries `?return=view`, so `SegmentRebuildController` answers with `ListController::viewAction` for that segment (the user stays on the page) instead of the list.

**Contacts badge and "Updated on" line on the segment's page** (WEL-77): `SegmentCampaignDetailStatusSubscriber` adds to its hidden marker the segment id, the contact count (`getRepository()->getLeadCount`), the texts and the contacts URL; `segment-campaign-detail-badges.js` draws the list's contacts badge (`.patches-count-badge`, 40px like the circles, blue with contacts / gray empty) FIRST in the badge box, and the line `.patches-segment-updated` (14px, right-aligned, same text as the list) as the last child of `.page-header` (inside `#toolbar` it is a flex item and squeezes the buttons). While the dropdown's Update runs, `SegmentUpdateLockSubscriber` swaps both like the list row (orange "Updating" badge, line becomes "."); it exposes `window.mauticPatchesSegmentDecorate` so the badge drawn later is swapped too. The page reloads when the rebuild ends.

**Last update date in the table**: the segment list shows only the contact count, so after clicking Update nothing in the table changed. `EventListener/SegmentLastBuiltSubscriber` uses the core hook `customContent('segment.name')` (called in `LeadBundle/Resources/views/List/_list_row.html.twig`) to add a muted line under the name: "Atualizado em <date>" / "Updated on <date>", or "Ainda não atualizado" when `LeadList::lastBuiltDate` is null. Template: `Resources/views/Segment/last_built.html.twig`. The date is NOT formatted with core's `dateToFull`: Mautic's date helpers call PHP `DateTime::format()` with the configured pattern (`date_format_full`, default `F j, Y g:i a T`), which always writes the month in English, even for a pt_BR user. `Service/LocalizedDateFormatter` uses `IntlDateFormatter` (php `intl`, present in the image) with the user's locale (`Translator::getLocale()`) and the user's time zone (converted by Mautic's `DateTimeHelper::getLocalDateTime()`), e.g. "2 de outubro de 2026 às 02:42". The other dates of Mautic (e.g. the 'date added' column) stay in the configured English pattern unless `date_format_*` is changed in Settings. The line and the contact count only refresh when the list is reloaded after the background rebuild ends; the click answers before that.

**Tests**: `SegmentRebuilderTest`, `SegmentListButtonSubscriberTest` (location, route, item type, permission). Live: `scripts/5/test-segment-rebuild-button.sh` logs in like a browser, checks the button is on a published row and not on an unpublished one, that a POST without the CSRF token does nothing, and that the click rebuilds the real segment and shows the flash.

**Tests (API)**: `Tests/Unit/Controller/Api/SegmentRebuildApiControllerTest` (404/403/409/202/500, nothing is launched on an error), `Tests/Unit/Service/SegmentRebuildLauncherTest` (the exact command, detached, quoting, no lock bypass). Live: `scripts/5/test-segment-rebuild-api.sh` empties a segment's membership in the database, calls the endpoint, waits for the real command to rebuild it and checks the members and last_built_date, plus 404/401/409 and two calls in a row.

**Update lock until the rebuild is done** (`EventListener/SegmentUpdateLockSubscriber`, route `mautic_patches_segment_rebuild_status`): the click only starts the rebuild in the background, so a user could click again and again. After a click, the Update of THAT segment (others stay free) is disabled and its "Updated on ..." line becomes "<spinner> Updating..." (bold, theme link color, soft pulse; the spinner is core's loader icon drawn as an inline SVG, because the icon font glyph sat off-center and wobbled). It stays so until the rebuild really ends:

```json
{
  "how it knows": "every 3 s the script asks GET /s/segment-rebuild/{id}/status (SegmentRebuildController::statusAction, session login, same edit access as the click, 404 for 'not found' and 'not allowed' alike) for the segment's last built date. The command writes it when it ENDS (UpdateLeadListsCommand, full rebuilds only), so the date seen at the click is the baseline and a different one means done.",
  "then": "the list (or the segment's own page) is reloaded if the user is still on it, which refreshes 'N contacts' and 'Updated on'; the button is free again.",
  "limits": "2 minutes without an end: freed and reloaded anyway. A failed check is retried. If the click itself fails (no permission) the button stays locked until that limit.",
  "nothing stored": "no localStorage / cookie / session; a reload of the page frees the button (the command's own per-segment lock still protects the segment).",
  "the page is replaced under the lock": "the click's answer redirects to the list, so the lock lives in a script variable and is applied again after every Mautic ajax page load (the script wraps Mautic.onPageLoad).",
  "tied to the template": "Resources/views/Segment/last_built.html.twig carries data-segment-id and data-updating-text; the script finds the line by .segment-last-built."
}
```

**Tests (update lock)**: the PHPUnit suite of the bundle, plus `Tests/js/segment-update-lock.test.js` (Node's own runner, no install): it takes the script from the PHP class and RUNS it against a fake browser (fake DOM and mQuery, fake timers, a recorder for the ajax calls): click and lock, second click swallowed, other segments free, line swap, lock put back after the page is replaced, polling every 3 s, unlock and same-page-only reload, 2-minute limit, retry, several segments. Run: `docker exec --user www-data mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"`. Live: `scripts/5/test-segment-update-status.sh` (status route answers, 404s, login redirect, what the list carries, and the round trip: baseline, click, date changes, equals the database). Run all three when core changes (a Mautic upgrade): the live one tells whether the routes, permissions and markup the scripts depend on still exist. Not covered: how it LOOKS (spinner, pulse), and the contact-page scripts have no Node tests yet.

**Production**: the PHP user of the web server (www-data) must be able to run `php bin/console`, and the container/server must allow a background process to outlive the request (true for Apache + mod_php or php-fpm, not for a platform that kills children). `cache:clear` after deploying, reading its output. No migration.

## "Update" in the campaign list and page

Same idea as the segment's Update, for campaigns (core has no such button, only the command `mautic:campaigns:rebuild`, alias `mautic:campaigns:update`).

```json
{
  "buttons": "EventListener/CampaignListButtonSubscriber: location list_actions on mautic_campaign_index and page_actions on mautic_campaign_action with objectAction=view (?return=view). Only with campaign:campaigns:editown/editother and a published campaign (Service/CampaignRebuilder::canRebuild).",
  "click": "POST /s/campaign-rebuild/{id} (CampaignRebuildController, session login, ajax CSRF) -> CampaignRebuilder::request -> CampaignRebuildLauncher: `nohup sh -c 'php bin/console mautic:campaigns:rebuild --campaign-id=N; rm -f MARKER' &`. A second click while running answers a flash 'already being updated' and launches nothing.",
  "done detection": "the campaign command saves no end date. A marker file var/logs/campaign-rebuild/campaign-rebuild-N.running is written BEFORE the launch (so the first poll is never 'done' by mistake) and removed by the shell when the command ends. GET /s/campaign-rebuild/{id}/status answers {running:bool}; a marker older than 1 hour is ignored. 404 for 'not found' and 'not allowed' alike.",
  "lock script": "SegmentUpdateLockSubscriber::script($poll, $max, 'campaign') (CampaignUpdateLockSubscriber injects it on page.header.left). Differences from the segment kind: done = running:false (no baseline date), and the first question waits one poll (3 s) so the click's POST has started. The page address used for the reload is read again at that first question, because the click's answer moves the page to the list core builds (/s/campaigns/1) and the search filter lives in the session; comparing with the address at click time never reloaded.",
  "line": "CampaignUpdatingLineSubscriber adds an EMPTY div .campaign-updating-line (customContent 'campaign.name'); the script fills it with the spinner and 'Updating...' while locked. No 'Updated on' line: the campaign has no last built date.",
  "tests": "PHPUnit (CampaignRebuilder, CampaignRebuildLauncher incl. a real launch with /bin/true, button, lock and line subscribers, status controller, routes); Tests/js/campaign-update-lock.test.js runs the real script; browser spec scripts/5/e2e/tests/campaign-update.spec.js (row menu, lock, spinner, reload at the end, page dropdown, unpublished). The row menu button in the list is `.dropdown-toggle` inside the row; the first one on the page is the disabled header one.",
  "production": "the web user must be able to write var/logs (marker folder is created on demand) and run php bin/console; cache:clear after deploying. No migration."
}
```

## Segment rebuild by contact id (switch "Segmentos por id")

Plugin settings, Features tab, field `keyset_rebuild` (Service/SegmentRebuild/KeysetRebuildSetting; not saved yet = ON). ON: `KeysetContactSegmentService` reads the contacts in batches ordered by id, each batch continues from the last id of the previous one. OFF: core batches. If the first batch fails, the rebuild falls back to core's own query.

- Default is ON, but there is NO proven speed gain: on 10k/20k seeded contacts the time was about the same as core (the time is `addLead` per member). One run: email segment 20k ON 226s vs OFF 182s (+24%); the other cases equal or faster. Do not promise speed in the UI or changelog.
- Members must be equal ON and OFF. Test: `scripts/5/test-segment-keyset-perf.sh` (default size 2000; `KEYSET_PERF_SIZES='10000 20000'` for the real timing, about 2 hours). It seeds and deletes its own data, checks members ON = OFF, a second run changes nothing, a low id that starts matching is found, manual add/remove stays, campaign members = segment members. Exit 1 on failure, WARN when ON is more than 2x slower.
