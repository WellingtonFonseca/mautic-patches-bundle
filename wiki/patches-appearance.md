# Patches: Appearance button and modal

A sun / moon button left of the notification bell opens the Appearance screen of the account (accent colors and theme cards) in core's shared modal, from any page. A choice applies at once and stays in the browser. Must not break: the button stays hidden on the account page.

## How it works

```json
{
  "button": "EventListener/AppearanceButtonSubscriber.php injects Resources/js/appearance-button.js (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, 'page.header.left'). The script puts an <li> first in `.navbar-right`, so it sits left of the bell. Icon: moon on light and solarized-light, sun on dark, solarized-dark and freire (a MutationObserver on the `theme` attribute of <html> keeps it right).",
  "modal body": "Controller/AppearanceModalController.php, route mautic_patches_appearance_modal (GET /s/appearance-modal), Resources/views/Appearance/modal.html.twig. It reuses core's @MauticUser/Helper/accent.html.twig and theme.html.twig and core's strings (mautic.user.account.appearance.*): no new translation. The theme list is a copy of core's Account tab (UserBundle Profile/index.html.twig): a theme added in core is added here by hand.",
  "choice": "A click on a radio sets the attribute of <html> (`theme`, `accent`) and the localStorage key `m-toggle-setting-<name>`, the key core reads on every page load (1.core.js). Nothing is saved on the server; the choice is per browser, as in core.",
  "checked radios": "The markup does not know the browser's choice: on `shown.bs.modal` the script marks each radio from localStorage, then the attribute of <html>, then the default (light, accent 01).",
  "opening": "Core binds its ajaxmodal handler only to the links of a loaded page, so the script handles the click and calls Mautic.ajaxifyModal itself."
}
```

## Traps

- The account page (`#appearance.tab-pane`) has radios with the same ids and names as the modal. The button is hidden there by CSS (`body:has(#appearance.tab-pane)`); Mautic keeps the navbar when it opens a page by ajax, so a rule on the URL would not follow.
- Core applies a choice only when the Save of the profile form is clicked. The modal has no form: never wait for a Save.
- The modal is widened with `.modal-dialog:has(.mautic-patches-appearance)`: the cards are a third of the body.
- A browser spec that sets the theme must do it AFTER opening the modal: `open()` loads the page again and the attribute is lost.

## Tests

- Tests/js/appearance-button.test.js (icon, choice, storage that throws), Tests/Unit/EventListener/AppearanceButtonSubscriberTest.php.
- scripts/5/e2e/tests/appearance-modal.spec.js: place left of the bell, icon per theme, modal body, a card and a color apply and persist, hidden on the account page and back by ajax.
