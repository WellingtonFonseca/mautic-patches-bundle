<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\FieldDefinition;

use Mautic\LeadBundle\Helper\FormFieldHelper;
use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reads the JSON of a field definition and returns its fields in one clean shape, or throws every problem of the file at once.
 */
class FieldDefinitionParser
{
    private const OBJECTS = ['lead', 'company'];
    private const GROUPS  = ['core', 'social', 'personal', 'professional'];
    private const FLAGS   = ['isPublished', 'isRequired', 'isListable', 'isVisible', 'isShortVisible', 'isPubliclyUpdatable', 'isUniqueIdentifier', 'isIndex'];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws FieldDefinitionImportException
     */
    public function parse(string $json): array
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new FieldDefinitionImportException([$this->translator->trans('mautic.patches.field_definition.error.json')]);
        }

        $errors = $this->checkHeader($data);
        if ([] !== $errors) {
            throw new FieldDefinitionImportException($errors);
        }

        $fields = [];
        $seen   = [];
        foreach ($data['fields'] as $position => $raw) {
            $name = is_array($raw) && is_string($raw['alias'] ?? null) ? $raw['alias'] : '#'.($position + 1);

            if (isset($seen[$name])) {
                $errors[] = $this->translator->trans('mautic.patches.field_definition.error.repeated', ['%field%' => $name]);

                continue;
            }
            $seen[$name] = true;

            $fieldErrors = $this->checkField($raw, $name);
            if ([] !== $fieldErrors) {
                array_push($errors, ...$fieldErrors);

                continue;
            }

            $fields[] = $this->normalize($raw, $position);
        }

        if ([] !== $errors) {
            throw new FieldDefinitionImportException($errors);
        }

        return $fields;
    }

    /**
     * @param array<mixed> $data
     *
     * @return string[]
     */
    private function checkHeader(array $data): array
    {
        if (FieldDefinitionExporter::FORMAT !== ($data['format'] ?? null)) {
            return [$this->translator->trans('mautic.patches.field_definition.error.format')];
        }

        if (FieldDefinitionExporter::VERSION !== ($data['version'] ?? null)) {
            return [$this->translator->trans('mautic.patches.field_definition.error.version')];
        }

        if (!is_array($data['fields'] ?? null) || [] === $data['fields']) {
            return [$this->translator->trans('mautic.patches.field_definition.error.no_fields')];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function checkField(mixed $raw, string $name): array
    {
        if (!is_array($raw)) {
            return [$this->translator->trans('mautic.patches.field_definition.error.not_object', ['%field%' => $name])];
        }

        $errors = [];
        foreach (['object', 'alias', 'label', 'type'] as $key) {
            if (!is_string($raw[$key] ?? null) || '' === trim($raw[$key])) {
                $errors[] = $this->translator->trans('mautic.patches.field_definition.error.missing_key', ['%field%' => $name, '%key%' => $key]);
            }
        }
        if ([] !== $errors) {
            return $errors;
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $raw['alias'])) {
            $errors[] = $this->translator->trans('mautic.patches.field_definition.error.alias', ['%field%' => $name]);
        }
        if (!in_array($raw['object'], self::OBJECTS, true)) {
            $errors[] = $this->translator->trans('mautic.patches.field_definition.error.object', ['%field%' => $name, '%value%' => $raw['object']]);
        }
        if (!array_key_exists($raw['type'], (new FormFieldHelper())->getTypes())) {
            $errors[] = $this->translator->trans('mautic.patches.field_definition.error.type', ['%field%' => $name, '%value%' => $raw['type']]);
        }
        if (isset($raw['group']) && !in_array($raw['group'], self::GROUPS, true)) {
            $errors[] = $this->translator->trans('mautic.patches.field_definition.error.group', ['%field%' => $name, '%value%' => (string) json_encode($raw['group'])]);
        }
        foreach (self::FLAGS as $flag) {
            if (isset($raw[$flag]) && !is_bool($raw[$flag])) {
                $errors[] = $this->translator->trans('mautic.patches.field_definition.error.flag', ['%field%' => $name, '%key%' => $flag]);
            }
        }
        if (isset($raw['properties']) && !is_array($raw['properties'])) {
            $errors[] = $this->translator->trans('mautic.patches.field_definition.error.properties', ['%field%' => $name]);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function normalize(array $raw, int $position): array
    {
        $field = [
            'object'          => $raw['object'],
            'alias'           => $raw['alias'],
            'label'           => $raw['label'],
            'type'            => $raw['type'],
            'group'           => $raw['group'] ?? 'core',
            'order'           => is_int($raw['order'] ?? null) ? $raw['order'] : $position + 1,
            'charLengthLimit' => is_int($raw['charLengthLimit'] ?? null) ? $raw['charLengthLimit'] : null,
            'defaultValue'    => is_scalar($raw['defaultValue'] ?? null) ? (string) $raw['defaultValue'] : null,
            'properties'      => $raw['properties'] ?? [],
        ];

        $defaults = ['isPublished' => true, 'isListable' => true, 'isVisible' => true];
        foreach (self::FLAGS as $flag) {
            $field[$flag] = $raw[$flag] ?? ($defaults[$flag] ?? false);
        }

        return $field;
    }
}
