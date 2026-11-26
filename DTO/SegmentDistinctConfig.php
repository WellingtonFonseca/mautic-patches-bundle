<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

/**
 * The DISTINCT setting of one segment: keep one contact (the lowest id) per value of the chosen fields.
 */
final class SegmentDistinctConfig
{
    /**
     * @param list<string> $fields aliases of contact fields, in the order chosen
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly bool $removeEmpty = false,
        public readonly array $fields = []
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->enabled === $other->enabled && $this->removeEmpty === $other->removeEmpty && $this->fields === $other->fields;
    }
}
