<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * The DISTINCT setting of a segment (tab "DISTINCT" of the segment form): one row per segment, tied to it by its id.
 * No row = DISTINCT off (core behaviour). The table is created by MauticPatchesBundle::onPluginUpdate().
 */
class SegmentDistinctValue
{
    public const TABLE_NAME = 'segment_distinct';

    /**
     * @param list<string> $fields
     */
    public function __construct(private int $segmentId, private bool $enabled, private bool $removeEmpty, private array $fields)
    {
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME);
        $builder->createField('segmentId', 'integer')->columnName('segment_id')->makePrimaryKey()->build();
        $builder->createField('enabled', 'boolean')->build();
        $builder->createField('removeEmpty', 'boolean')->columnName('remove_empty')->build();
        $builder->createField('fields', Types::JSON)->build();
    }

    public function getSegmentId(): int
    {
        return $this->segmentId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isRemoveEmpty(): bool
    {
        return $this->removeEmpty;
    }

    /**
     * @return list<string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
