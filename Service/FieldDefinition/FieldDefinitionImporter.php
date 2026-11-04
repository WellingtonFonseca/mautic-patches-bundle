<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\FieldDefinition;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Field\Exception\AbortColumnCreateException;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\MauticPatchesBundle\DTO\FieldDefinitionImportResult;
use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Makes the fields of this instance match a field definition: creates the fields it lacks and switches fields on or off. A field
 * that exists keeps its label, type and options. The check (apply = false) saves nothing. Unlike the import of a Custom Object, the
 * apply is not one transaction: a new field adds a column to the leads or companies table, and MySQL commits that change at once.
 * So the whole file is validated before the first field is saved.
 */
class FieldDefinitionImporter
{
    public function __construct(
        private FieldModel $fieldModel,
        private FieldDefinitionParser $parser,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @throws FieldDefinitionImportException
     */
    public function import(string $json, bool $apply = true): FieldDefinitionImportResult
    {
        $plan   = $this->plan($this->parser->parse($json));
        $result = $plan->result();

        if (!$apply) {
            return $result;
        }

        return $result->withColumnsInBackground($this->apply($plan->newFields(), $plan->toggled()));
    }

    /**
     * @param array<int, array<string, mixed>> $wanted
     *
     * @throws FieldDefinitionImportException
     */
    private function plan(array $wanted): FieldDefinitionPlan
    {
        $plan   = new FieldDefinitionPlan();
        $fields = $this->fieldsByAlias();

        foreach ($wanted as $entry) {
            $existing = $fields[$entry['alias']] ?? null;

            if (null === $existing) {
                $this->planNew($plan, $entry);

                continue;
            }

            $this->planExisting($plan, $entry, $existing);
        }

        $plan->assertValid();

        return $plan;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function planNew(FieldDefinitionPlan $plan, array $entry): void
    {
        $name  = $entry['object'].': '.$entry['alias'];
        $field = $this->buildField($entry);

        $plan->fail($this->violations($field, $name));
        $plan->create($name, $field);
    }

    /**
     * Only the status of a field that exists can change: its label, type and options stay.
     *
     * @param array<string, mixed> $entry
     */
    private function planExisting(FieldDefinitionPlan $plan, array $entry, LeadField $existing): void
    {
        $name = $entry['object'].': '.$entry['alias'];

        if ($existing->getObject() !== $entry['object']) {
            $plan->fail([$this->translator->trans('mautic.patches.field_definition.error.other_object', ['%field%' => $entry['alias'], '%object%' => $existing->getObject()])]);

            return;
        }

        if ($existing->getType() !== $entry['type']) {
            $plan->keepType($name);
        }

        if ($existing->getIsPublished() === $entry['isPublished']) {
            $plan->keepAsIs();

            return;
        }

        if ($entry['isPublished']) {
            $plan->activate($name, $existing);

            return;
        }

        if ($existing->getIsUniqueIdentifier()) {
            $plan->keepIdentifier($name);

            return;
        }

        $plan->deactivate($name, $existing, $this->segmentNames($existing));
    }

    /**
     * @return array<string, LeadField> every field of this instance, by alias (unique in both objects)
     */
    private function fieldsByAlias(): array
    {
        $fields = [];
        foreach ($this->fieldModel->getRepository()->findAll() as $field) {
            $fields[$field->getAlias()] = $field;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function buildField(array $entry): LeadField
    {
        $field = new LeadField();
        $field->setObject($entry['object']);
        $field->setAlias($entry['alias']);
        $field->setLabel($entry['label']);
        $field->setType($entry['type']);
        $field->setGroup($entry['group']);
        $field->setOrder($entry['order']);
        $field->setIsPublished($entry['isPublished']);
        $field->setIsRequired($entry['isRequired']);
        $field->setIsListable($entry['isListable']);
        $field->setIsVisible($entry['isVisible']);
        $field->setIsShortVisible($entry['isShortVisible']);
        $field->setIsPubliclyUpdatable($entry['isPubliclyUpdatable']);
        $field->setIsUniqueIdentifier($entry['isUniqueIdentifier']);
        $field->setIsIndex($entry['isIndex']);
        $field->setCharLengthLimit($entry['charLengthLimit']);
        $field->setDefaultValue($entry['defaultValue']);
        $field->setProperties($entry['properties']);

        return $field;
    }

    /**
     * @return string[]
     */
    private function violations(LeadField $field, string $name): array
    {
        $messages = [];
        foreach ($this->validator->validate($field) as $violation) {
            $messages[] = $name.': '.$this->translator->trans((string) $violation->getMessage(), $violation->getParameters(), 'validators');
        }

        return $messages;
    }

    /**
     * @return string[]
     */
    private function segmentNames(LeadField $field): array
    {
        $names = [];
        foreach ($this->fieldModel->getFieldSegments($field) as $segment) {
            $names[] = (string) $segment->getName();
        }

        return $names;
    }

    /**
     * @param array<string, LeadField>              $new
     * @param array<int, array{0: LeadField, 1: bool}> $toggle
     *
     * @return string[] the new fields whose column a background job makes
     */
    private function apply(array $new, array $toggle): array
    {
        $inBackground = [];
        foreach ($new as $name => $field) {
            try {
                $this->fieldModel->saveEntity($field);
            } catch (AbortColumnCreateException) {
                // core saved the field and a background job makes the column (create_custom_field_in_background)
                $inBackground[] = $name;
            }
        }

        $switched = [];
        foreach ($toggle as [$field, $published]) {
            $field->setIsPublished($published);
            $switched[] = $field;
        }
        if ([] !== $switched) {
            $this->fieldModel->getRepository()->saveEntities($switched);
        }

        return $inBackground;
    }
}
