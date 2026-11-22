categoria: ui
regras:
- The notification (`#flashes.alert-growl-container`) belongs to the SCREEN, not the page: `position:fixed` (core's absolute put it at the top of a long scrolled list, out of sight).
- A notification stays 10 s (notice), 15 s (warning, `alert-growl--warning`) or 20 s (error, `alert-growl--error`); the mouse over one holds it and 4 s after it leaves it goes; the close button closes at once; a caller that passes autoClose false is obeyed. Core shows `flashes` of any JSON answer, 422 included.
- Dark themes: the toast (`.alert-growl`) is the opposite of the page in core (inverse layer, light in the dark themes), where the yellow warning link (#FDB933) and the red of an error (#F86B4F) cannot be read: `color-mix(var(--layer-02) 94%, transparent)` with a shadow, `--text-primary` for the text, plain links and the close button; the colored left border and the yellow and red links stay core's; light themes keep core's dark toast.
- A spec of timing uses a fake clock (`page.clock`).
