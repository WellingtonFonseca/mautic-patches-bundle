# Mautic Patches Bundle

Mautic 5 plugin that holds small, targeted fixes for bugs in Mautic's own
**core** (not this workspace's other plugins), applied through Mautic's
normal plugin extension points — event subscribers, no core files ever
edited. This repo is a shared home for that kind of fix in general; it
isn't scoped to any single bug, and new, unrelated fixes are expected to
be added here over time as they come up.

## Requirements

- PHP >= 8.1
- `mautic/core-lib` ^5.0

## Installing into a Mautic instance

Mautic autoloads plugins from `docroot/plugins/<PluginDirectoryName>` (or
`docroot/app/plugins/...`) — the directory name must exactly match the
plugin's bundle class name.

1. Place this repository's contents at:
   ```
   <mautic-install>/docroot/plugins/MauticPatchesBundle
   ```
   In this project's Docker setup that's done via a bind mount in
   `docker-compose.yml`:
   ```yaml
   volumes:
     - ../mautic-patches-bundle:/var/www/html/docroot/plugins/MauticPatchesBundle:z
   ```
   applied to `mautic_web`, `mautic_cron`, and `mautic_worker` (all three
   need the code, not just the web container).

2. Clear the cache and install/register the plugin:
   ```bash
   docker exec --user www-data mautic-mautic_web-1 php /var/www/html/bin/console cache:clear
   docker exec --user www-data mautic-mautic_web-1 php /var/www/html/bin/console mautic:plugins:install
   ```
   (Adjust the container name if it differs from `mautic-mautic_web-1`.)

   Mautic's compiled cache is baked into the image and does **not** know
   about a newly mounted plugin — skipping `cache:clear` means the plugin
   silently never shows up, with no error. This also reverts every time
   the containers are recreated (`docker compose down` + `up`, not just a
   restart), so re-run it after that too.

## Fixes included

Each row is one independent fix — its own event subscriber, triggered by
one of Mautic's own extension points, with no dependency on the others.
New fixes get added as new rows here, not by rescoping this list.

| Fix | Where | Subscriber | Confirmed on |
|---|---|---|---|
| Clicking "Locate" on a Jump to Event campaign action shows a full-canvas overlay to spotlight the source/target nodes, but the overlay has no click handler of its own — any other click is swallowed until the same "Locate" link is clicked again, and even then the toggle can end up a step out of sync (needing a second click to take effect) | Campaign Builder | `EventListener/CampaignBuilderOverlaySubscriber.php` | Mautic 5.2 |
| In the Dark theme, hovering or pressing "Save & Close" or "Cancel" turns the button white with white text. Core styles the second primary button of a row (and a secondary after two primaries) as "tertiary" by position, with a hard-coded white text on hover/pressed, over the dark theme's light background (contrast 1.1:1 and 1.7:1). Only the Dark theme is affected; it now gets the theme's dark text color on those states (never on disabled buttons). Also fixes other plugins that use the same buttons. | Every admin form | `EventListener/DarkThemeButtonSubscriber.php` | Mautic 5.2 |
| The buttons at the bottom of a modal form (Save, Save & Close, Close — on a plugin's settings and other modals) sit small, at the right edge. Core moves them into `.modal-footer .modal-form-buttons`, a flex row pushed right (`justify-content: flex-end`) with every button a fixed width; only a lone button grows. They now share the whole width of the modal footer equally (`flex: 1 1 0; width: auto`); the footer's gap and the rounded outer corners stay core's. | Every modal with form buttons | `EventListener/ModalFormButtonsSubscriber.php` | Mautic 5.2 |
| In the Dark and Solarized Dark themes, pages laid out with `.bg-white` / `.bg-auto` columns (the Custom Objects detail and form pages, for one) show almost the whole screen in white with dark text. Core hard-codes both classes as `#fff !important` (and `.modal .box-layout .bg-auto` as `#fff`) while the themes only change CSS variables. Only the two dark themes are overridden, with the variables core's own panels use (`--background`, `--border-subtle`, `--text-primary`). | Every admin page with those classes (Custom Objects detail/form pages) | `EventListener/DarkThemeBackgroundSubscriber.php` | Mautic 5.2 |
| Not a bug, a look: the Light theme is pure white (page, panels, fields), which blows out the screen. It becomes a light gray (`#f2f2f2` page), keeping the layers' hierarchy, by overriding the theme's CSS variables under `:root[theme="light"]`; `.bg-white`/`.bg-auto` follow. On the gray page the faint grays of the core vanish (the past dots and the line of the "recent activity" feed, wells, tags, borders), so the layer family (`--layer-01` `#e0e0e0`, hover, selected, accent) and the subtle borders are a step darker, and the surfaces the core paints with a fixed light gray (striped/hovered table rows, breadcrumb, unchecked toggle switch, menu divider) take those variables. Only the `light` theme; the values are in two constants to tune. | Every admin page, Light theme | `EventListener/LightThemeBackgroundSubscriber.php` | Mautic 5.2 |
| Not a bug, a layout choice: on a campaign's page the statistics / map block comes before the journey preview, which ends up far down the page. The preview (with its Actions and Contacts tabs) now comes first and the statistics block after it. A script moves the core's `.stats-menu` and `.stats-menu__content` nodes (keeping their ids and handlers) after the preview on the first load and after every Mautic ajax page load, then fires a window resize for the charts; it only acts on a page that has `#preview-container`. | Campaign page | `EventListener/CampaignViewOrderSubscriber.php` | Mautic 5.2 |
| Not a bug, a usability choice: on a contact's page the "Details" block (the contact's fields) starts closed and needs a click every time. It now opens as soon as the page is in place; the toggler still closes and opens it. A script adds the `in` class to `#lead-details` and drops `collapsed` from the toggler on the first load and after every Mautic ajax page load, once per block (so it never reopens one the user closed); pages without `#lead-details` are not affected. | Contact page | `EventListener/ContactViewDetailsOpenSubscriber.php` | Mautic 5.2 |
| Not a bug, a layout choice: on a contact's page the right column (points, contact data, address...) takes a quarter of the screen. A button at the top of the middle column hides and shows it, and the middle column uses the whole width while it is hidden. The column ALWAYS starts hidden: the choice is never remembered (no localStorage, cookie or session). A script on the first load and after every Mautic ajax page load finds the page by `#lead-details`, hides the `.col-md-3` next to it and adds the button, once per loaded page; other pages are not affected. | Contact page | `EventListener/ContactViewSidePanelSubscriber.php` | Mautic 5.2 |
| Not a bug, a safeguard: the segment's "Update" (this bundle's rebuild button) answers at once while the rebuild runs in the background, so a user can click it again and again and get a notification each time. After a click, the Update button of THAT segment (the others stay free) is locked until the rebuild is DONE: disabled (its icon unchanged), and further clicks are swallowed before core's ajax handler. In the list, that segment's "Updated on ..." line shows "<spinner> Updating..." meanwhile, bold, in the theme's link color and pulsing softly (a fade, not switched off by the reduced-motion setting) (core's loader icon drawn as an inline SVG, not through the icon font, whose glyph sat off-center and wobbled). When the time is up, the list (or the segment's page, if still on it) reloads, which refreshes "N contacts" and "Updated on". Applied again after every ajax page load; nothing is stored in the browser. The server's own per-segment lock stays the real protection. | Segment list and segment page | `EventListener/SegmentUpdateLockSubscriber.php` | Mautic 5.2 |

## Additions to Mautic (not fixes)

The bundle also holds small additions to Mautic's own behaviour, such as API endpoints, when core has no way to do it. Same rule: no core file is edited.

| Addition | Where | Code |
|---|---|---|
| `POST /api/segments/{id}/rebuild` recalculates one segment now, like `mautic:segments:update --list-id={id}`, instead of waiting for the cron. It starts the real command in the background and answers `202` at once; progress is the segment's last built date. Errors: 404 no segment, 403 no edit access, 409 segment not published. | REST API | `Controller/Api/SegmentRebuildApiController.php`, `Service/SegmentRebuildLauncher.php`, route in `Config/config.php` |
| "Update" in the three-dots menu of each row of the segment list (next to Edit/Clone/Delete) and in the dropdown of the segment's own page (next to Clone/Delete): recalculates that segment now. Shown only when the user may edit the segment and it is published. It POSTs (ajax, with Mautic's CSRF token) to `/s/segment-rebuild/{id}` and shows a flash message; from the segment's own page it stays on that page (`?return=view`). | Segment list | `EventListener/SegmentListButtonSubscriber.php`, `Controller/SegmentRebuildController.php`, `Service/SegmentRebuilder.php` (shared with the API) |
| Under each segment's name in the segment list: "Updated on <date>" (the segment's last built date, in the user's time zone) or "Not updated yet". The list had no such information, so nothing in the table said whether an Update had run. | Segment list | `EventListener/SegmentLastBuiltSubscriber.php`, `Resources/views/Segment/last_built.html.twig` (via core's `customContent('segment.name')`) |

## Adding a new fix

1. One `EventListener/*Subscriber.php` class per fix, hooking into a core
   event (`CoreEvents::VIEW_INJECT_CUSTOM_CONTENT` for injecting JS/CSS
   into existing pages, or whatever event fits the bug being patched).
2. A unit test under `Tests/Unit/EventListener/`.
   (A class with a plain `string` constructor argument must not sit in a folder Mautic autowires: declare it in `Config/services.php`, as `SegmentRebuildLauncher` is.)
3. A new row in the "Fixes included" table above.

## Running the test suite

```bash
docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && /var/www/html/vendor/bin/phpunit"
```

Requires `phpunit/phpunit` as a dev dependency on the image — see
[wiki/docker-mautic5.md](../wiki/docker-mautic5.md) ("PHPUnit / automated
tests") for how that's set up in this project's Docker stack.

### JavaScript tests (Node, no extra dependencies)

The scripts these subscribers put on the page are also run for real, against a small
fake browser (a fake DOM and `mQuery`, fake timers, a recorder for the ajax calls).
`Tests/js/segment-update-lock.test.js` takes the script straight from the PHP class
(through `php -r`), so it tests exactly what the page gets: the click, the lock, the
polling every 3 s, the unlock, the reload only on the same page, the 2-minute limit, a
failed check retried, several segments at once, the lock put back after the page is replaced.

```bash
docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
```

### Live test (real server)

`mautic/scripts/test-segment-update-status.sh` logs in like a browser and checks the
`/s/segment-rebuild/{id}/status` route (answer, 404s, login redirect, GET only), what the
segment list carries for the script, and the whole round trip: baseline date, the click, the
date changing, and it being the one in the database. Run it with the stack up.

When core changes (for example the move to Mautic 7), run all three: PHPUnit, the Node tests and
the live script. The live one is what tells whether the routes, the permissions and the markup the
scripts depend on (`.segment-last-built`, `a[href*="/s/segment-rebuild/"]`) still exist.
