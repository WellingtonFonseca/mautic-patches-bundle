<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * The prefix of a segment (Compras, Vendas...), the way a category is: one row per segment, kept apart from the name.
 * A segment has no custom fields, so it lives in a table of this plugin, tied to the segment by its id. The table is
 * created by MauticPatchesBundle::onPluginUpdate().
 */
class SegmentPrefixValue
{
    public const TABLE_NAME = 'segment_prefix';

    public function __construct(private int $segmentId, private string $prefix)
    {
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME);
        $builder->createField('segmentId', 'integer')->columnName('segment_id')->makePrimaryKey()->build();
        $builder->createField('prefix', 'string')->length(191)->build();
        $builder->addIndex(['prefix'], 'segment_prefix_prefix');
    }

    public function getSegmentId(): int
    {
        return $this->segmentId;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }
}
