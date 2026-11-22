categoria: ui
regras:
- Core hard-codes colors (#fafafa, #fff, #fcf8e3 ...) that the themes cannot reach because the themes only change CSS variables (`:root[theme=...]`): fix by CSS with the variables, never by editing core files.
- Scope each fix to the theme that is broken, under `:root[theme="..."]` (so the order of the stylesheets does not matter, `!important` only where core uses it); the other themes keep the core look. Dark fixes are for Dark and Solarized Dark only; the Light theme changes are for 'light' only (Solarized Light and Freire untouched).
- Dark themes: striped rows and hover use `var(--layer-01)` / `var(--layer-hover-01)`; rows marked new (`tr.warning`) `color-mix(in srgb, var(--support-warning) 16%, var(--layer-01))` (26% on hover); a table inside a table (`.table .table`) `transparent`; `.bg-white` / `.bg-auto` columns `var(--background)`, `var(--border-subtle)`, `var(--text-primary)`.
- Light theme: the gray look is made by overriding the theme variables (page #f2f2f2, layer family, subtle borders a step darker), with every value in constants (VARIABLES, RULES) with the core value in a comment, to tune. Row lines use `--table-line` (#b0b0b0: the core lines vanished on hover); lines and circles of the campaign builder use `--campaign-line` (#8d8d8d, about 3:1 on the page).
- Always read the contrast in both a dark and a light theme: specs check dark, solarized-dark and light; a `color-mix()` value is read back as `color(srgb ...)` with channels 0 to 1.
- A label chip must use `label-gray` (colors from the theme variables `--label-*-gray`): `label-default` has a fixed light gray, unreadable in the dark theme.
- Any new element uses the theme variables (`var(--background)`, `var(--text-primary)`, `--border-subtle` ...), never fixed colors, so both themes follow.
- Toast, notification and menu hover: [ui-notification.md](ui-notification.md), [ui-button.md](ui-button.md).
