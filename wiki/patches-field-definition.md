# Patches: clone of the contact and company fields (definition JSON)

The list of custom fields (Settings > Custom Fields, route `mautic_contactfield_index`) has two page actions: "Exportar definição" shows every contact and company field as JSON, "Importar definição" reads that JSON on another instance. The import creates the fields the instance lacks and switches fields on or off. What must not break: the file keeps no ids, a check never saves, and a field that exists keeps its label, type and options.

## The JSON

```json
{
  "where": "Service/FieldDefinition/FieldDefinitionExporter (toArray / toJson). Page: Controller/FieldDefinitionController::exportAction, template Field/export_definition.html.twig (a <pre>, read only; the Copy button reads its text and shows core's toast through Mautic.setFlashes(Mautic.addInfoFlashMessage(text)); the text is in data-copied).",
  "keys": "format 'custom-field-definition', version 1, fields[] {object (lead | company), alias, label, type, group, order, isPublished, isFixed, isRequired, isListable, isVisible, isShortVisible, isPubliclyUpdatable, isUniqueIdentifier, isIndex, charLengthLimit, defaultValue, properties}",
  "every field": "Active AND inactive, native AND created, so the person never wonders why a field is not in the file. isPublished is the status the import copies.",
  "left out": "ids, dates, creators.",
  "order": "findBy object, order, id."
}
```

## The import

```json
{
  "where": "Controller/FieldDefinitionController (importFormAction GET, importSaveAction POST), Form/Type/FieldDefinitionImportType (name field_definition_import), Field/import_definition.html.twig. Logic: Service/FieldDefinition/FieldDefinitionParser (the file), FieldDefinitionImporter (compare and apply), FieldDefinitionPlan (what the import will do, built entry by entry), FieldDefinitionImportAlerts (the alerts of the check and of the result).",
  "two steps": "Conferir posts with hidden `confirmed` 0: the importer runs with apply=false, nothing is saved, the page lists what WOULD be done. Then the same text read only and Confirmar (confirmed 1) applies it. After the apply the same page shows the result as alerts and the back button. Limpar empties the text and brings Conferir back, in the browser. A check with no change hides the save button (class hide) so Limpar can bring it back.",
  "new alias": "creates the field with every value of the file (FieldModel::saveEntity, which adds the column). Validated with ValidatorInterface first (label, unique alias, reserved words).",
  "existing alias": "only isPublished can change. The label, type, group, options and order stay. A different type is a warning (keptType). The alias is unique across both objects: the same alias on the other object is an error.",
  "unique identifier": "never deactivated (email, companyname). Core's API would do it and the contacts would lose their deduplication. The check lists it (keptIdentifier).",
  "deactivate": "the check lists the segments that filter on the field (FieldModel::getFieldSegments) as a warning. Core does not block it. Campaigns that use the field are not listed.",
  "errors": "every problem is collected and thrown together (FieldDefinitionImportException): bad JSON, format or version, no fields, a repeated alias, a missing key, an alias not in the form [a-z][a-z0-9_]*, an unknown object, type (FormFieldHelper::getTypes) or group, a flag that is not a boolean, an alias of the other object, and the violations of a new field. Nothing is saved when there is one.",
  "permission": "lead:fields:full for the two pages and for the buttons (EventListener/FieldDefinitionButtonSubscriber, location page actions of mautic_contactfield_index).",
  "form token": "the POST needs the form's token: a page of another site cannot post an import."
}
```

## Traps

- The apply is NOT one transaction. A new field adds a column to leads or companies and MySQL commits that change at once. The whole file is validated before the first field is saved, so a failure in the middle is a database failure, and the fields already created stay.
- With create_custom_field_in_background on, FieldModel::saveEntity saves the field, throws AbortColumnCreateException and a job makes the column later. The importer reports those fields (alert `background`).
- A field whose column is not created yet has isPublished false in core until the job runs.
- The path `/s/field-definition/import` is taken by core's `/s/{object}/import` (the list of Importações). The routes are `/s/field-definition/clone-export` and `/s/field-definition/clone-import`.
- The core API lets an admin deactivate a fixed field, even `email`. Only this importer protects the unique identifiers.
- The back button goes in the `indexButton` block (Field/_back_button.html.twig). The pt_BR text of `mautic.core.close_back` names its placeholder `%alvo%`: both `%target%` and `%alvo%` are passed.
- Plugins stay independent: the form and the page copy the ones of the Custom Objects import on purpose (../mc-cs-plugin-custom-objects-5/wiki/custom-objects-definition-clone.md).

## Tests

- PHPUnit: Tests/Unit/Service/FieldDefinition (parser, importer, exporter, alerts), Tests/Unit/EventListener/FieldDefinitionButtonSubscriberTest, Tests/Unit/Config/RoutesTest.
- Browser: scripts/5/e2e/tests/field-definition.spec.js (buttons, export, check, apply, deactivate, activate, errors, back button, toast, dark theme). It makes its own fields with a unique alias and deletes them through the API (that drops the column).
