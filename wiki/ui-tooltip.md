categoria: ui
regras:
- Tooltips of buttons use `data-trigger="hover"`: with core's default (hover focus) the tooltip of a clicked button stays while it has focus and covers the row above.
- Bootstrap hides a tooltip on the mouseleave of its button: a button hidden or removed with the mouse still over it never gets it and its tooltip (drawn in the body) stays on the canvas: destroy the tooltip when the button is hidden or removed. Reproduce such a bug by hovering a button and then removing or hiding it with the mouse still; scenarios that move the mouse away never show it.
