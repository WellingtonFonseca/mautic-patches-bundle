<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\FieldDefinition;

use Mautic\LeadBundle\Entity\LeadField;
use MauticPlugin\MauticPatchesBundle\DTO\FieldDefinitionImportResult;
use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;

/**
 * What a field definition import will do, built entry by entry: the fields to create, the fields to switch on or off, what stays as it
 * is and every error. Nothing is saved here. Each name is "object: alias".
 */
class FieldDefinitionPlan
{
    /** @var array<string, LeadField> */
    private array $new = [];

    /** @var array<int, array{0: LeadField, 1: bool}> */
    private array $toggle = [];

    /** @var array<string, string[]> */
    private array $lists = ['created' => [], 'activated' => [], 'deactivated' => [], 'keptType' => [], 'keptIdentifier' => []];

    /** @var array<string, string[]> field => names of the segments that filter on it */
    private array $inUse = [];

    /** @var string[] */
    private array $errors = [];

    private int $unchanged = 0;

    public function create(string $name, LeadField $field): void
    {
        $this->new[$name]         = $field;
        $this->lists['created'][] = $name;
    }

    public function activate(string $name, LeadField $field): void
    {
        $this->toggle[]            = [$field, true];
        $this->lists['activated'][] = $name;
    }

    /**
     * @param string[] $segments the names of the segments that use the field
     */
    public function deactivate(string $name, LeadField $field, array $segments): void
    {
        $this->toggle[]              = [$field, false];
        $this->lists['deactivated'][] = $name;

        if ([] !== $segments) {
            $this->inUse[$name] = $segments;
        }
    }

    public function keepType(string $name): void
    {
        $this->lists['keptType'][] = $name;
    }

    public function keepIdentifier(string $name): void
    {
        $this->lists['keptIdentifier'][] = $name;
    }

    public function keepAsIs(): void
    {
        ++$this->unchanged;
    }

    /**
     * @param string[] $messages
     */
    public function fail(array $messages): void
    {
        array_push($this->errors, ...$messages);
    }

    /**
     * @throws FieldDefinitionImportException when any entry had a problem
     */
    public function assertValid(): void
    {
        if ([] !== $this->errors) {
            throw new FieldDefinitionImportException($this->errors);
        }
    }

    /**
     * @return array<string, LeadField>
     */
    public function newFields(): array
    {
        return $this->new;
    }

    /**
     * @return array<int, array{0: LeadField, 1: bool}>
     */
    public function toggled(): array
    {
        return $this->toggle;
    }

    public function result(): FieldDefinitionImportResult
    {
        return new FieldDefinitionImportResult(
            $this->lists['created'],
            $this->lists['activated'],
            $this->lists['deactivated'],
            $this->inUse,
            $this->lists['keptType'],
            $this->lists['keptIdentifier'],
            $this->unchanged
        );
    }
}
