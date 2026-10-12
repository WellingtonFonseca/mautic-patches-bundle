<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue;

class MauticPatchesBundle extends PluginBundleBase
{
    /**
     * Mautic only creates a plugin's tables on first install, and this plugin was installed before it had one: the
     * segment prefix table is created here, on the version bump that introduced it, and only when missing.
     *
     * @param array<string, \Doctrine\ORM\Mapping\ClassMetadata<object>>|null $metadata
     */
    public static function onPluginUpdate(Plugin $plugin, MauticFactory $factory, $metadata = null, ?Schema $installedSchema = null): void
    {
        if (!empty($metadata[SegmentPrefixValue::class]) && (null === $installedSchema || !$installedSchema->hasTable(SegmentPrefixValue::TABLE_NAME))) {
            self::installPluginSchema([$metadata[SegmentPrefixValue::class]], $factory);
        }
    }
}
