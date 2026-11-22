# Patches: campaign page order and event modal

The campaign page order (journey preview first) and the wide event modal of the builder. What must not break: the modal width follows its content. The counts of a node on the Preview tab open nothing: the contacts of a step are listed on the "Etapas" tab (wiki/patches-campaign-steps-tab.md). The canvas (pan, wait strip, circles, disconnect button): wiki/patches-campaign-builder.md.

## What it does

```json
{
  "campaign page order": "EventListener/CampaignViewOrderSubscriber.php: a LAYOUT change. On a campaign's page the statistics / map block came first and the journey preview (with its Actions and Contacts tabs) ended up far down. A script moves core's '.stats-menu' and '.stats-menu__content' nodes after the preview ('#preview-container' tab content), keeping their ids and handlers, on the first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad, so core's campaign scripts have already run), then fires a window resize so the charts fit. It only acts on a page that has the preview, and does nothing if the block is already below it. Checked on the real page's structure and in a Node simulation.",
  "campaign event modal": "EventListener/CampaignEventModalWidthSubscriber.php: a LAYOUT change. The modal where an action, condition or decision is configured in the campaign builder (#CampaignEventModal, core's CampaignBundle Campaign/_builder.html.twig) is as wide as the contact details modal (Padrão: [ui-modal.md](ui-modal.md), width 1100px, max-width 95vw). A style injected on page.header.left like the other subscribers; only that modal. Tests: scripts/5/test-campaign-event-modal.sh and scripts/5/e2e/tests/campaign-condition.spec.js (width measured in the browser)."
}
```
