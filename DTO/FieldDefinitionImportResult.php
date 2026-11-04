<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

/**
 * What a field definition import does (or would do). Each list holds "object: alias" lines of the fields it names.
 */
class FieldDefinitionImportResult
{
    /**
     * @param string[]              $created
     * @param string[]              $activated
     * @param string[]              $deactivated
     * @param array<string, string[]> $deactivatedInUse  field => names of the segments that filter on it
     * @param string[]              $keptType            fields that exist with another type
     * @param string[]              $keptIdentifier      unique identifier fields the file would deactivate
     * @param string[]              $columnsInBackground created fields whose column a background job makes
     */
    public function __construct(
        private array $created,
        private array $activated,
        private array $deactivated,
        private array $deactivatedInUse,
        private array $keptType,
        private array $keptIdentifier,
        private int $unchanged,
        private array $columnsInBackground = []
    ) {
    }

    /**
     * @param string[] $names
     */
    public function withColumnsInBackground(array $names): self
    {
        $copy                      = clone $this;
        $copy->columnsInBackground = $names;

        return $copy;
    }

    /** @return string[] */
    public function getCreated(): array
    {
        return $this->created;
    }

    /** @return string[] */
    public function getActivated(): array
    {
        return $this->activated;
    }

    /** @return string[] */
    public function getDeactivated(): array
    {
        return $this->deactivated;
    }

    /** @return array<string, string[]> */
    public function getDeactivatedInUse(): array
    {
        return $this->deactivatedInUse;
    }

    /** @return string[] */
    public function getKeptType(): array
    {
        return $this->keptType;
    }

    /** @return string[] */
    public function getKeptIdentifier(): array
    {
        return $this->keptIdentifier;
    }

    /** @return string[] */
    public function getColumnsInBackground(): array
    {
        return $this->columnsInBackground;
    }

    public function getUnchanged(): int
    {
        return $this->unchanged;
    }

    public function hasChanges(): bool
    {
        return [] !== $this->created || [] !== $this->activated || [] !== $this->deactivated;
    }
}
