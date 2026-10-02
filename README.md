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

## Additions to Mautic (not fixes)

The bundle also holds small additions to Mautic's own behaviour, such as API endpoints, when core has no way to do it. Same rule: no core file is edited.

| Addition | Where | Code |
|---|---|---|
| `POST /api/segments/{id}/rebuild` recalculates one segment now, like `mautic:segments:update --list-id={id}`, instead of waiting for the cron. It starts the real command in the background and answers `202` at once; progress is the segment's last built date. Errors: 404 no segment, 403 no edit access, 409 segment not published. | REST API | `Controller/Api/SegmentRebuildApiController.php`, `Service/SegmentRebuildLauncher.php`, route in `Config/config.php` |
| "Update" in the three-dots menu of each row of the segment list (next to Edit/Clone/Delete): recalculates that segment now. Shown only when the user may edit the segment and it is published. It POSTs (ajax, with Mautic's CSRF token) to `/s/segment-rebuild/{id}` and shows a flash message. | Segment list | `EventListener/SegmentListButtonSubscriber.php`, `Controller/SegmentRebuildController.php`, `Service/SegmentRebuilder.php` (shared with the API) |

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
