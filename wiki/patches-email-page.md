# patches: the email page

The email page (Channels > Emails > open one) and its edit page: the order of the blocks and a "Histórico" tab with the audit log. Core is not edited. What must not break: moving a node keeps its handlers (tabs, date range, map).

```json
{
  "order": "EventListener/EmailViewOrderSubscriber: a LAYOUT change, same technique as the campaign and segment pages. The tabs Contagem de cliques / Contatos come first; the block Estatísticas / Mapa (`.stats-menu` and `.stats-menu__content`) is moved after the `.tab-content` that holds `#clicks-container`. The script is Service/StatsBlockScript (shared with the campaign page: anchor selector, window flag and variable name are its parameters). It runs on 'page.header.left' and acts on the first load and after every Mautic ajax load (it wraps Mautic.onPageLoad) and fires a window resize for the charts. It does nothing when the block is already below.",
  "history tab": "EventListener/EmailAuditTabSubscriber. Core writes the audit_log of an email ({field: [old, new]}) and its page only prints 'Atividade recente' with no detail. The tab 'Histórico' reads it (AuditLogModel::getLogForObject('email', id, ..., 'email')) and shows it as a table. Edit page: core's hooks 'email.tabs' (link) and 'email.tabs.content' (pane) of @MauticEmail/Email/form.html.twig, priority -100 so it is the LAST tab (after Variáveis N8N and Campanhas of the other plugin, priority 0 and -10). View page: core has no hook for that block, so the hook 'details.stats.graph.below' (just before the tabs) renders the link and the pane in hidden holders and a script (mQuery(fn): runs at DOM ready, or at once after an ajax load) moves them into the tabs. Only for a saved email.",
  "table": "Resources/views/Email/_audit_pane.html.twig (shared by audit_tab and audit_form_link and audit_form_pane): Data | Usuário | Ação | Campo | Antes | Depois, one row per field, date / user / action span their rows, values in `pre.patches-audit-value` (style inline in the pane: the plugin has no stylesheet). Service/AuditLog/AuditTrail makes the rows (values cut at 300 characters, arrays as JSON)."
}
```

## Traps

- The log is ordered by date to the second: an edit in the same second as the create can show first.
- The fields show as stored (`subject`, `fromName`, `dateModified`, `lists: [] -> []`): core writes noise on every save and the tab does not filter it yet; no translation of the field names; the date is core's `dateToFull` (English).
- The view-page script runs where the hook is, before the tabs exist: it must stay inside `mQuery(function () { ... })`.
- A copy of the table for SMS and HSM lives in N8nDispatchBundle (../n8n-dispatch-bundle-5/wiki/n8n-dispatch-template-history.md), on purpose.

## Tests

PHPUnit: EmailViewOrderSubscriberTest, EmailAuditTabSubscriberTest, AuditTrailTest. Browser: `scripts/5/e2e/tests/email-view-order.spec.js`, `email-audit-tab.spec.js` (view page, edit page, new email has no tab; it waits 1.1 s between the create and the edit for the ordering).
