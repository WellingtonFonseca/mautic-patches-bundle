# MauticPatchesBundle wiki

This folder is the wiki of the MauticPatchesBundle plugin (core UI fixes and small additions to Mautic 5). Each file is either a PATTERN (a rule we always follow across features: it starts with `categoria:` and `regras:`) or a FUNCIONALIDADE (how one feature works). Read the pattern files of the area before touching a screen, then only the feature file of that area. A feature file points to its patterns with a line starting with `Padrão:`. Start with [mautic-patches-bundle.md](mautic-patches-bundle.md) for the overview.

## Padrões

### ui

- [ui-badge.md](../../wiki/ui-badge.md) (shared, root wiki) — the status badge look, the icon badge, the hover opening and the nowrap rule; twins of the n8n badge.
- [ui-status-column.md](../../wiki/ui-status-column.md) (shared, root wiki) — the Status column of the lists and the Ativar / Desativar item of the row menu.
- [ui-botao.md](ui-botao.md) — button hover, danger items of the row menu, the dark theme text of buttons.
- [ui-modal.md](ui-modal.md) — modal footers, form buttons, single primary button, widths, long text in modal tables.
- [ui-notificacao.md](ui-notificacao.md) — position, times and dark look of the toast notification.
- [ui-tema.md](ui-tema.md) — rules of every dark / light theme fix (variables, scope, contrast).
- [ui-tooltip.md](ui-tooltip.md) — tooltip trigger and tooltips of buttons that go away.

## Funcionalidades

- [mautic-patches-bundle.md](mautic-patches-bundle.md) — overview of the plugin (start here: what it is, the areas and where to read).
- [patches-lists-status.md](patches-lists-status.md) — list status badges and toggles (Ativar / Desativar), plugin tiles, notifications.
- [patches-status-column.md](patches-status-column.md) — the Status column (icon badge) of the segment, campaign, user, email and custom field lists, the campaign reentry badge.
- [patches-status-badges-contacts-emails.md](patches-status-badges-contacts-emails.md) — the Status column of the contact list (Email, SMS, HSM by Do Not Contact) and the badges of the email list (type, A/B test, translation, draft).
- [patches-themes.md](patches-themes.md) — dark and light theme fixes, the toast, the footer of the confirmation modal.
- [patches-appearance.md](patches-appearance.md) — theme button of the navbar, Appearance modal (accent, theme).
- [patches-campaign-builder.md](patches-campaign-builder.md) — campaign builder canvas (overlay, pan, wait strip, disconnect button, circles).
- [patches-campaign-pages-and-modals.md](patches-campaign-pages-and-modals.md) — campaign page order, wide event modal.
- [patches-campaign-steps-tab.md](patches-campaign-steps-tab.md) — the Etapas tab of the campaign page (actions and conditions in flow order, badges per step, the contacts of a state, Recalculate dates).
- [patches-campaign-node-ui.md](patches-campaign-node-ui.md) — counts of a node (Preview), tooltips of the node buttons.
- [patches-campaign-wait-schedule.md](patches-campaign-wait-schedule.md) — the lines under the wait text of a node (hours, days), the date text of a wait in the user's language and zone, the capital of Esperar.
- [patches-campaign-wait.md](patches-campaign-wait.md) — campaign action Wait and Recalculate dates.
- [patches-campaign-contact-condition.md](patches-campaign-contact-condition.md) — campaign condition Contact fields.
- [patches-segment-update.md](patches-segment-update.md) — segment rebuild API and the Update buttons.
- [patches-segment-screens.md](patches-segment-screens.md) — segment page, Filtros tab, filter chips, details modal.
- [patches-email-page.md](patches-email-page.md) — the email page (order of the blocks, the Histórico tab on the page and the edit page; see also the n8n template history).
- [patches-contact-screens.md](patches-contact-screens.md) — contact pages, contact tables and modals.
- [patches-contact-preferences.md](patches-contact-preferences.md) — Preferências option (Email / SMS on or off) in the row menu of the contact list, the save that stays on the list.
- [patches-contact-bulk-delete.md](patches-contact-bulk-delete.md) — bulk delete of contacts (gear of the contact list: the table with its filter bar as the search, count, typed confirmation, batches).
- [patches-prefix.md](patches-prefix.md) — required Prefixo of segments and campaigns.
- [patches-performance.md](patches-performance.md) — performance monitor.
- [patches-field-definition.md](patches-field-definition.md) — clone of the contact and company fields (Exportar / Importar definição on the custom fields list).
- [patches-forms-and-misc.md](patches-forms-and-misc.md) — date and time fields, translation overrides, add button of the lists of values, email builder modal, plugin icon, left menu that opens on hover.
