<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\FieldDefinition;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

/**
 * The definition of every contact and company field, active or not, as the JSON the import page of another instance reads.
 * No ids and no dates: a field is named by its object and alias. Inactive fields are in the file on purpose, so that the import
 * can switch them off too and nobody wonders why a field is missing.
 */
class FieldDefinitionExporter
{
    public const FORMAT  = 'custom-field-definition';
    public const VERSION = 1;

    public function __construct(private FieldModel $fieldModel)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $fields = $this->fieldModel->getRepository()->findBy([], ['object' => 'ASC', 'order' => 'ASC', 'id' => 'ASC']);

        return [
            'format'  => self::FORMAT,
            'version' => self::VERSION,
            'fields'  => array_map(fn (LeadField $field): array => $this->fieldToArray($field), $fields),
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldToArray(LeadField $field): array
    {
        return [
            'object'              => $field->getObject(),
            'alias'               => $field->getAlias(),
            'label'               => $field->getLabel(),
            'type'                => $field->getType(),
            'group'               => $field->getGroup(),
            'order'               => $field->getOrder(),
            'isPublished'         => $field->getIsPublished(),
            'isFixed'             => $field->isFixed(),
            'isRequired'          => (bool) $field->isRequired(),
            'isListable'          => (bool) $field->getIsListable(),
            'isVisible'           => (bool) $field->getIsVisible(),
            'isShortVisible'      => (bool) $field->getIsShortVisible(),
            'isPubliclyUpdatable' => (bool) $field->getIsPubliclyUpdatable(),
            'isUniqueIdentifier'  => (bool) $field->getIsUniqueIdentifier(),
            'isIndex'             => $field->isIsIndex(),
            'charLengthLimit'     => $field->getCharLengthLimit(),
            'defaultValue'        => $field->getDefaultValue(),
            'properties'          => $field->getProperties(),
        ];
    }
}
