<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * The prefix of a campaign (Compras, Vendas...), the way a category is: one row per campaign, kept apart from the name.
 * A campaign has no custom fields, so it lives in a table of this plugin, tied to the campaign by its id. The table is
 * created by MauticPatchesBundle::onPluginUpdate().
 */
class CampaignPrefixValue
{
    public const TABLE_NAME = 'campaign_prefix';

    public function __construct(private int $campaignId, private string $prefix)
    {
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME);
        $builder->createField('campaignId', 'integer')->columnName('campaign_id')->makePrimaryKey()->build();
        $builder->createField('prefix', 'string')->length(191)->build();
        $builder->addIndex(['prefix'], 'campaign_prefix_prefix');
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
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
