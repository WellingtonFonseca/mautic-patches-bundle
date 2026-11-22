# Patches: node counts and node button tooltips

The small layout rules of a campaign node: the counts (done / waiting) of the Preview tab and the tooltips of the node buttons of the builder. Both are core markup (`Event/_preview.html.twig`, `Event/_generic.html.twig`) that is not touched; the plugin adds a style or a script. Do not undo: the `!important` and the removal below are there for a reason.

## Counts of a node (Preview tab)

```json
{
  "where": "EventListener/CampaignNodeStatStyleSubscriber::STAT_STYLE, injected on page.header.left: `.campaign-event-stat{margin-left:auto;padding-left:24px!important}`.",
  "what": "the counts (check = done, hourglass = waiting) of EVERY node sit at the right end of the node, with at least 24px from the text. They open nothing (no pointer cursor): the contacts of a step are on the second \"Ações\" tab. Core puts them right after the text (a flex gap), so a long line like 'Esperar 12 dias depois do inicio' touched them.",
  "why !important": "core's utility `pa-4` (padding 4px) on `.campaign-event-stat` is !important: a plain padding loses. `margin-left:auto` alone only moves the counts when the node has room to spare, and a node is as wide as its text.",
  "types": "action, condition and decision nodes all use `.campaign-event-stat` inside `.campaign-event-content`; the builder has no counts.",
  "tests": "e2e/tests/campaign-preview-wait.spec.js ('the counts of every node keep a gap', reads the padding and the distances of three nodes, light and dark screenshots); CampaignNodeStatStyleSubscriberTest."
}
```

## Tooltips of the node buttons (builder)

```json
{
  "rule": "the Clonar / Editar / Remover buttons of a node have NO tooltip. Core puts `data-toggle=\"tooltip\"` on the icon inside each button (the `<a>` keeps core's aria-label, which stays).",
  "bug": "Bootstrap hides a tooltip on the mouseleave of its button. A button that goes away under the mouse (the node was removed; the buttons hidden with the `hide` class when the edit modal opens) never gets it: the tooltip, drawn in the body, stays on the canvas over the buttons of the nodes around until the page reloads. Seen in production after clone, edit, cancel.",
  "fix": "EventListener/CampaignNodeButtonTooltipSubscriber + Resources/js/campaign-node-button-tooltips.js (page.header.left, installed once): `.campaign-event-buttons [data-toggle=\"tooltip\"]` is destroyed through Bootstrap and loses data-toggle, title, data-original-title, data-placement (and a tooltip that is up is removed). A MutationObserver strips buttons that come into the page later (a clone, a new node).",
  "not touched": "every other tooltip, such as the counts of the Preview tab.",
  "rejected": "a sweeper that removes the tooltips left behind (button gone or hidden): it worked, but any new way to lose the mouseleave brought the bug back. Removing the tooltip cannot fail.",
  "tests": "e2e/tests/campaign-node-tooltip.spec.js (no tooltip on hover, none after remove, none after the late insertion of buttons, the counts keep theirs); Tests/js/campaign-node-button-tooltips.test.js; CampaignNodeButtonTooltipSubscriberTest."
}
```

## Traps

- The clone button of a node of a campaign made through the API answers 200 and adds no node on the page in Playwright: a spec cannot count nodes after a clone. To test late buttons, append the markup to `#CampaignCanvas` by `page.evaluate`.
- Reproduce a tooltip bug by hovering a button, then removing or hiding it with the mouse still: scenarios that move the mouse away never show it.
- A change of the injected style or script needs `cache:clear` and `apachectl graceful` (../wiki/docker-mautic5.md), or the page still carries the old one.
