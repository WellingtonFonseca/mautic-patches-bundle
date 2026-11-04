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
        $plan = $this->plan($this->parser->parse($json));

        if (!$apply) {
            return $plan['result'];
        }

        return $plan['result']->withColumnsInBackground($this->apply($plan['new'], $plan['toggle']));
    }

    /**
     * @param array<int, array<string, mixed>> $wanted
     *
     * @return array{result: FieldDefinitionImportResult, new: array<string, LeadField>, toggle: array<int, array{0: LeadField, 1: bool}>}
     *
     * @throws FieldDefinitionImportException
     */
    private function plan(array $wanted): array
    {
        $errors = [];
        $lists  = ['created' => [], 'activated' => [], 'deactivated' => [], 'keptType' => [], 'keptIdentifier' => []];
        $inUse  = [];
        $new    = [];
        $toggle = [];
        $same   = 0;
        $fields = $this->fieldsByAlias();

        foreach ($wanted as $entry) {
            $name     = $entry['object'].': '.$entry['alias'];
            $existing = $fields[$entry['alias']] ?? null;

            if (null === $existing) {
                $field = $this->buildField($entry);
                array_push($errors, ...$this->violations($field, $name));
                $new[$name]         = $field;
                $lists['created'][] = $name;

                continue;
            }

            if ($existing->getObject() !== $entry['object']) {
                $errors[] = $this->translator->trans('mautic.patches.field_definition.error.other_object', ['%field%' => $entry['alias'], '%object%' => $existing->getObject()]);

                continue;
            }

            if ($existing->getType() !== $entry['type']) {
                $lists['keptType'][] = $name;
            }

            if ($existing->getIsPublished() === $entry['isPublished']) {
                ++$same;

                continue;
            }

            if ($entry['isPublished']) {
                $lists['activated'][] = $name;
                $toggle[]             = [$existing, true];

                continue;
            }

            if ($existing->getIsUniqueIdentifier()) {
                $lists['keptIdentifier'][] = $name;

                continue;
            }

            $lists['deactivated'][] = $name;
            $toggle[]               = [$existing, false];
            $segments               = $this->segmentNames($existing);
            if ([] !== $segments) {
                $inUse[$name] = $segments;
            }
        }

        if ([] !== $errors) {
            throw new FieldDefinitionImportException($errors);
        }

        $result = new FieldDefinitionImportResult(
            $lists['created'],
            $lists['activated'],
            $lists['deactivated'],
            $inUse,
            $lists['keptType'],
            $lists['keptIdentifier'],
            $same
        );

        return ['result' => $result, 'new' => $new, 'toggle' => $toggle];
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
