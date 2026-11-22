# Patches: Preferences option of the contact list row menu

The three-dots menu of each row of the contact list has "Preferências". It opens core's preferences modal (channels Email and SMS on or off, frequency, preferred channel). A save keeps the user on the list. Do not break: the modal stays core's (same URL, form and save), and a save from the contact page stays core's.

## What it does

```json
{
  "button": "EventListener/ContactPreferencesButtonSubscriber.php (CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, location LOCATION_LIST_ACTIONS, route mautic_contact_index, priority 140). The attributes copy core's button of the contact page (Lead/lead.html.twig): data-toggle ajaxmodal, data-target #MauticSharedModal, href mautic_contact_action objectAction contactFrequency, the core keys mautic.lead.contact.frequency (label) and mautic.lead.lead.header.contact.frequency (header, %name% = primary identifier). No own translation key. Only for who has lead:leads:editown / editother on the contact (hasEntityAccess), like core. The item must be a Lead entity.",
  "what the modal saves": "A channel checkbox that is checked = the contact accepts the channel; unchecked = a Do Not Contact row (lead_donotcontact, reason 3 'manual') for it. Core's controller (LeadController::contactFrequencyAction) does the save.",
  "stay on the list": "EventListener/ContactPreferencesStayOnListSubscriber.php (KernelEvents::RESPONSE). Core answers a good save with closeModal + the contact PAGE as newContent. When the POST of contactFrequency has a Referer of /s/contacts or /s/contacts/{page}, the subscriber reads that list URL with a GET sub request (X-Requested-With XMLHttpRequest, same session and cookies, so the search and the page stay) and uses its JSON (newContent, route, ...) in place, with closeModal 1 and updateMainContent true. Core's JS then closes the modal and draws the list.",
  "not touched": "A save from the contact page (Referer /s/contacts/view/{id}), no Referer, a form with errors (no closeModal in its answer), the GET that opens the modal, another objectAction, a sub request, a request with no session, and a list that cannot be read (non 2xx or no newContent, a warning is logged with the URL and status): core's answer goes out as it is. The list path comes from the router (mautic_contact_index), not a fixed /s/contacts."
}
```

## Traps

- The modal answer with `closeModal` and WITHOUT `newContent` closes the modal and leaves the page as it is (Mautic.processModalContent). With `newContent` the page content is replaced. That is why the list's own JSON is used, not a bare close.
- The form's action is fixed by core (no way to pass "come back to the list" in it), so the Referer is the only signal. It is the URL of the page that made the ajax call.
- The option exists only on route mautic_contact_index. The contact tables of a segment or campaign (ContactsTableTrait) use other routes and get no button.
- Infection: the two priorities (140 and 0) and the host of the sub request URL escape on purpose. They do not change the result.

## Tests

- Tests/Unit/EventListener/ContactPreferencesButtonSubscriberTest, Tests/Unit/EventListener/ContactPreferencesStayOnListSubscriberTest.
- Browser spec scripts/5/e2e/tests/contact-preferences.spec.js: own contact, row menu, email off then on through the modal, the DNC read through the API, the list still on screen, both themes.
