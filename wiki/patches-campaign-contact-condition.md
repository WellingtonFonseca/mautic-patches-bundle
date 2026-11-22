# Patches: campaign condition Contact fields

The campaign condition "Contact fields": several criteria on contact and company fields, evaluated by the segment engine.

## What it does

```json
{
  "campaign contact fields condition": "ADDITION, see the section \"Campaign condition: Contact fields\" of this file."
}
```

## Campaign condition: Contact fields

A NEW condition `lead.field_values` (pt_BR "Campos de contato", en_US "Contact fields") next to core's `lead.field_value`, which stays untouched: core evaluates its condition in a fixed branch of LeadBundle's CampaignSubscriber with no point to extend, and one criterion only.

```json
{
  "stored": "properties field, operator, value (the first criterion, like every other condition) and additionalCriteria [{field, operator, value}, ...]. field is the alias of a contact or company field. ALL criteria must hold.",
  "fields and operators": "Service/ContactFields/ContactFieldsCatalog: the 'lead' and 'company' groups of ListModel::getChoiceFields() (the segment filter's own list, so the operators agree, including in / !in on text), minus type leadlist / tags and the operator notInCustomObjects. It needs a web request: the choices of the segment screen fail on the command line (TypeOperatorSubscriber asks AssetModel, which needs a user).",
  "evaluation": "Service/ContactFields/ContactFieldsMatcher: a LeadList that is never saved gets the criteria as filters, ContactSegmentFilterFactory::getSegmentFilters + ContactSegmentQueryBuilder::assembleContactsSegmentQueryBuilder(0, ...) build the query, and `l.id = :contact` restricts it to the contact. So including / excluding, relative dates, empty and company fields behave as in a segment. The matcher reads the fields from FieldModel::getFieldListWithProperties (object, type, whether it is a list), NOT from the catalog: the campaign runs on the command line. A value A|B|C of in / !in becomes an array only for a list field (options, country, region, timezone, locale); a text field takes the string. A criterion on a field that does not exist makes the condition false.",
  "event": "ContactFieldsConditionSubscriber registers it on CAMPAIGN_ON_BUILD with eventName `mautic.patches.on_campaign_trigger_contact_fields` and formTheme @MauticPatches/Campaign/contact_fields_condition_widget.html.twig; the campaign dispatches a ConditionEvent (a CampaignExecutionEvent) and the result is set with setResult. A condition placed directly after the contact source never runs, as for every condition: put an action first.",
  "node name": "core prints the translation of `mautic.campaign.<type>` on the node: `mautic.campaign.lead.field_values` is in Translations (en_US and pt_BR). Without it the node reads the key.",
  "form": "Form/Type/ContactFieldsConditionType (first criterion + the additionalCriteria collection) and ContactFieldsCriterionType (field select in two optgroups, Contato / Empresa, each option carrying data-operators, data-options, data-field-type and data-object; the operator select is not validated against the field's operators because the browser replaces its options). The rows follow their field in Resources/js/contact-fields-condition.js (window.mauticPatchesContactFields, injected by ContactFieldsConditionScriptSubscriber): a text input, a select for a list field, a multiple select for including / excluding on a list (the value is kept in a hidden input as A|B|C), a calendar for dates, no value for empty / not empty. The first row has no input-group; the added rows have one (with the remove addon).",
  "chips": "Resources/js/segment-in-chips.js scanRows decorates the value input of a row whose operator is in / !in and whose input is a text one: `.cf-criterion` rows here and `.co-criterion` rows of the Custom Objects condition. The old chips are removed whenever a row's input is replaced (events `cf:criterion-updated` / `co:criterion-updated`). In an added row the chips box and the copy addon join the row's input-group; in the first row they get a group of their own.",
  "semantics that differ from what one would guess": "`empty` on a number also matches 0 (the segment reads it that way); `!in` on a company field leaves out the contacts that HAVE one of those companies (not 'has a company that is not in the list'). Both are the segment's behaviour, kept.",
  "tests": "scripts/5/test-campaign-condition-contact.sh (form, evaluation against the tables, a real campaign run by the commands: the run found the command-line trap above), Tests/Unit/EventListener/ContactFieldsConditionSubscriberTest.php, Tests/js/contact-fields-condition.test.js, scripts/5/e2e/tests/campaign-condition-contact.spec.js."
}
```
