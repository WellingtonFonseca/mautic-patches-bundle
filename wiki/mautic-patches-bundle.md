# MauticPatchesBundle (core fixes and small additions)

## What this is

A Mautic plugin for things that belong to Mautic itself, not to the Custom Objects or n8n plugins. Core files are never edited. It is general-purpose: UI fixes and small API additions both live here. Own git repo at `/home/paperclip/project/mautic-patches-bundle-5` (Mautic 5, branch main = mautic5-base), bind-mounted in the three containers of each stack (see [../wiki/docker-mautic5.md](../../wiki/docker-mautic5.md)). Its README has the list and the rules for adding to it.

## Where to read

Read only the file of the area you touch:

- [wiki/patches-lists-status.md](patches-lists-status.md) — The active / inactive status of the segment, campaign, user and email lists (the `.patches-badge`, Ativar / Desativar first in the row menu), the loader of the list toggles, the plugin tiles of Settings > Plugins, the fixed notifications, the delete hover and the live mode icon.
- [wiki/patches-status-column.md](patches-status-column.md) — The Status column (icon badge, second column) of the segment, campaign, user and email lists, the campaign reentry badge and the `.patches-badge` look.
- [wiki/patches-themes.md](patches-themes.md) — Fixes of the Dark, Solarized Dark and Light themes: striped table rows, button hover, modal form buttons, white columns, table lines and the light gray page.
- [wiki/patches-appearance.md](patches-appearance.md) — The sun / moon button left of the notification bell and the Appearance modal (accent colors and theme cards): where it is injected, how a choice is kept, why it is hidden on the account page.
- [wiki/patches-campaign-builder.md](patches-campaign-builder.md) — The campaign builder canvas: overlay, pan, wait strip, disconnect button, circles of the nodes.
- [wiki/patches-campaign-pages-and-modals.md](patches-campaign-pages-and-modals.md) — The campaign page order and the wide event modal.
- [wiki/patches-campaign-steps-tab.md](patches-campaign-steps-tab.md) — The "Etapas" tab of the campaign page: a type badge and three badges per action or condition step, the contacts of a state in one table, the Recalculate dates box of a Wait step.
- [wiki/patches-campaign-wait-schedule.md](patches-campaign-wait-schedule.md) — The lines under the wait text of a node (fixed hour, window of hours, days of the week), the one formatter of the builder and the Preview tab, the date of a wait in the user's language and time zone, and the capital of "Esperar".
- [wiki/patches-campaign-wait.md](patches-campaign-wait.md) — The campaign action Wait (`patches.wait_relative`): a date per contact counted from a date field of the contact or of a Custom Object item, the scheduler subclass, Recalculate dates with simulation, the command and the box of the Ações tab.
- [wiki/patches-campaign-contact-condition.md](patches-campaign-contact-condition.md) — The campaign condition "Contact fields": several criteria on contact and company fields, evaluated by the segment engine.
- [wiki/patches-segment-update.md](patches-segment-update.md) — `POST /api/segments/{id}/rebuild` (async, runs the real segments command), the Update button and its lock until the rebuild is done on the segment list and page, and the same on the campaign.
- [wiki/patches-segment-screens.md](patches-segment-screens.md) — The segment page: title, order (chart last), Filtros tab with values as badges, the filter selector, the duplicate button, including / excluding with chips on text fields, and the details modal on the list name.
- [wiki/patches-contact-screens.md](patches-contact-screens.md) — Contact Details open by default, the "Mais >" button that hides the right column, the contact list modal, the contact tables on the segment and campaign pages (columns follow Contact Settings) and the width the modals share.
- [wiki/patches-contact-preferences.md](patches-contact-preferences.md) — the Preferências option of the contact list row menu (core's modal) and the save that stays on the list
- [wiki/patches-contact-bulk-delete.md](patches-contact-bulk-delete.md) — The "Excluir em lote" page of the contact list: the table with its filter bar as the search, the count, the red Excluir button, the typed confirmation and the delete in batches.
- [wiki/patches-email-page.md](patches-email-page.md) — The email page: Estatísticas / Mapa below the click count and the contacts, and the "Histórico" tab (audit log as a table) on the page and the edit page.
- [wiki/patches-prefix.md](patches-prefix.md) — The required Prefixo on segments (own table, Segment Settings, form select, list column with sorting, automatic alias) and on campaigns (same model), and the PREFIX - Name shown in titles and lists.
- [wiki/patches-performance.md](patches-performance.md) — The Server-Timing header, the Performance screen and the `mautic:performance:report` / `mautic:performance:diagnose` commands.
- [../wiki/plugin-changelog.md](../../wiki/plugin-changelog.md) — The CHANGELOG.md by day, its tab in the settings modal of the plugin (Settings > Plugins > Mautic Patches) and the minimal integration that gives the patches that tile.
- [wiki/patches-field-definition.md](patches-field-definition.md) — The clone of the contact and company fields: Exportar / Importar definição on the custom fields list (JSON with every field and its status, check then confirm, create and switch on or off, unique identifiers kept).
- [wiki/patches-forms-and-misc.md](patches-forms-and-misc.md) — The date and time fields formatted while typing, the translation overrides of the pt_BR pack, the email builder validation error modal, the plugin icon and the left menu that opens on hover (15px strip).
