# Patches: bulk delete of contacts

The "Excluir em lote" page of the contact list: the contact list table with its filter bar as the search, a count, a red Excluir button and a delete that runs in batches. A wrong import can leave thousands of contacts; the list deletes 100 at a time. The delete must only take the search that was counted, and an empty search must never reach it.

## What it does

```json
{
  "entry": "The gear (page actions) of /s/contacts has a red 'Excluir em lote' item (EventListener/ContactBulkDeleteButtonSubscriber.php, class `danger`, priority -100, plain ajax link). It opens the page GET /s/contacts-bulk-delete. Red only on hover, like core's delete items.",
  "permission": "lead:leads:deleteother (ContactBulkDeleteController::PERMISSION), one rule for the page, the three endpoints and the menu item. A JSON endpoint answers 403 without it.",
  "page": "Resources/views/Contact/bulk_delete.html.twig holds the markup and the texts (data- attributes) only. The behavior is Resources/js/contact-bulk-delete.js, injected on the admin pages by EventListener/ContactBulkDeleteScriptSubscriber (page.header.left, like the other patches scripts). Core calls `Mautic.patchesContactBulkDeleteOnLoad` when the page loads (mauticContent patchesContactBulkDelete) and `Mautic.contactBulkDeleteContactsOnLoad(container, response)` after EVERY load of the table (mauticContent contactBulkDeleteContacts: first load, live search, sorting, paging). Top to bottom: the alert slot (#contact-bulk-delete-banner), the progress (only while it deletes), the form: the guide text (#contact-bulk-delete-guide, always visible, normal text) and the table of the contact list.",
  "endpoints": {
    "GET /s/contacts-bulk-delete/{objectId}/contacts/{page}": "The table (see 'table'); objectId is always 0 (the pagination builds its links from it). Its response carries `contactsTotal` and `contactsSearch`: the total of the search it answered and that search. A role without the permission gets an empty table and no total (no 403).",
    "POST /s/contacts-bulk-delete/batch  (form field search)": "Deletes up to 200 contacts of the search (a batch does not count the whole search) and answers {deleted, done}. done is true when the batch was not full (nothing is left). Empty or blank search: 400, and the service refuses it too. Needs the X-CSRF-Token header (core refuses a POST without it with a 200 and a flash)."
  },
  "which contacts a search means": "The contact list adds `!is:anonymous` to every search (core's LeadController) unless the search itself has `is:anonymous`: visitors are hidden. ContactBulkDeleter::force($search) is that rule, the ONE place for the table (spec key forceFilter of the trait), the count and the delete. Without it the delete would take visitors the list hides.",
  "service": "Service/ContactBulkDelete/ContactBulkDeleter (LeadModel + translator): deleteBatch(), force(). A blank search throws InvalidArgumentException before it reads anything (second layer after the controller's 400). It reads with LeadModel::getEntities (filter string, order l.id ASC) and deletes with LeadModel::deleteEntities, which dispatches the delete events. The deleted contacts leave the result set, so every batch reads page 1. BATCH_SIZE is 200."
}
```

## The table

```json
{
  "component": "The same ContactsTableTrait as the segment and campaign tabs (wiki/patches-contact-screens.md). Without a joinTable in the spec it reads the contact list's own query (LeadModel::getEntities), not getEntityContacts.",
  "bar": "The first load (a GET with ?search=, no tmpl) comes with core's filter bar. The bar's live search, sorting and paging read the search from the session (mautic.contactBulkDelete.contact.filter), set by the first load. The page always loads the table with an empty search.",
  "delete button": "The red Excluir button is the bar's `list_actions` (spec key toolbarActions of the trait: HTML at the end of the bar, the place of 'Novo'). It is rendered by Resources/views/Contact/bulk_delete_button.html.twig as part of the table's first load, so it comes back disabled every time the table loads. The page script finds it by id when it uses it and listens by delegation.",
  "the bar is the search": "There is NO separate count request. The total comes with every load of the table (`contactsTotal`, from the same query the table shows, so the number and the rows always agree). On every input in the bar (#search-contact-bulk-delete-result) the script turns the button off and hides the alert; the table that core's live search loads then brings the total, and onTable turns the button on for that search (`searched`) and shows the red alert. An answer whose `contactsSearch` is not the text now in the bar is dropped (core also cancels an older request, so this is a second layer). The delete posts `searched`, never the live text.",
  "typing does not filter, Enter does": "In a browser the core live search filters the table while the user types. A spec that fills the box must press Enter."
}
```

## The delete

```json
{
  "confirmation": "Core's Mautic.showConfirmation (the same modal as every delete). The button carries data-message (the question, set by the script from the count), data-confirm-text, data-cancel-text and data-confirm-callback=patchesBulkDeleteRun. The script adds a box between the title and the footer: the word of the language (EXCLUIR, DELETE: keys confirm_word and confirm_type) must be typed (case and edge spaces ignored) before #confirm turns on; Enter confirms. The footer comes from confirmation-footer.js, which moves #confirm and Cancel; the box goes after .modal-header.",
  "progress": "On confirm the script hides the alert and the form (guide, table with its bar and button) and shows #contact-bulk-delete-progress: an info banner, a bar (percent) and 'N de M excluídos'. It posts batches until `done` is true (runBatches). The percent is capped at 100.",
  "end": "The form comes back, the table loads again with an empty search, and the alert slot shows the green result; core's toast (Mautic.setFlashes) repeats it. A failed batch shows a red alert with how many were deleted before; the form and an empty table come back and the user can search again and repeat. The table reloads a moment after the alert: a text typed before that is lost.",
  "alert slot": "ONE element, above the guide text, for the total (danger, red; 'info' for none found), a failure (danger) and the result (success)."
}
```

## Traps

- Core's `.alert` sets its own `display`, which wins over the `hidden` attribute. A hidden alert stays on screen. The page has `#contact-bulk-delete-banner[hidden]{display:none!important}`.
- Padrão: [ui-modal.md](ui-modal.md) (core copies `.bottom-form-buttons` into the footer and hides the original): that is why the page does not use a modal and why a button in the table is found by id at use time.
- The delete follows the total that came with the table for the text in the bar; any change of the text turns the delete off until the next table answer. Never read the delete's search from the live text.
- An empty search matches every contact. The batch endpoint (400), the service (exception) and the button (off without a counted search) refuse it. Because the service refuses it too, breaking the controller's guard by hand and running the live script is safe: the batch then fails with a 500 instead of deleting.
- The total of the progress is read once (when the table answered). Contacts that match later are deleted too, so `deleted` can pass the total.
- A batch does not count the whole search (that cost one COUNT per 200 contacts). `done` comes from the batch being short; with an exact multiple of 200 the last request deletes 0 and ends the loop.
- The controller extends CommonController: the trait needs only `security`, `translator`, `user`, `getModel`, `delegateView` and `setListFilters`.
- A contact that fails to delete stops the batch with an error; the next try starts from what is left.

## Tests

```json
{
  "unit": "Tests/Unit/Service/ContactBulkDelete/ContactBulkDeleterTest.php (batch done / not done / none, a blank search never reaches the model, the anonymous rule), Tests/Unit/EventListener/ContactBulkDeleteButtonSubscriberTest.php, ContactBulkDeleteScriptSubscriberTest.php.",
  "node": "Tests/js/contact-bulk-delete.test.js: fill, foundText, percent, wordMatches, runBatches (the loop of batches: progress, done, a failure). The glue with the page is covered by the browser spec.",
  "live": "scripts/5/test-contacts-bulk-delete-access.sh: the 403 of a role without lead:leads:deleteother on the page and the batch; the table brings no total and no rows for a role without the permission; the 400 of an empty or blank search on the batch; the batch without the CSRF token deletes nothing; the real delete. Makes its own contacts and roles.",
  "browser": "scripts/5/e2e/tests/contact-bulk-delete.spec.js: the page, the bar as the search (total, alerts, button), the progress (the server's answers are faked, nothing is deleted), the answer of an older text (held) never wins, no delete without a counted search, the typed word, the real delete of 5 contacts, anonymous visitors, more than one batch (450 contacts, three requests: skipped in test-e2e, it runs ALONE as scripts/5/test-contacts-bulk-delete-volume.sh in the `first` lane, because a segment rebuild of another test meets a contact deleted a moment before and fails on a foreign key), the empty search, the gear item (red on hover, both themes). The tests that use the 5 contacts of beforeAll come BEFORE the one that deletes them.",
  "shared table": "A change to ContactsTableTrait or contacts_table.html.twig also runs scripts/5/test-contacts-tables.sh and campaign-steps-tab.spec.js."
}
```
