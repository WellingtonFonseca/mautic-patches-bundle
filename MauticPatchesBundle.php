<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\OverridingTranslationLoaderPass;
use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\RelativeWaitPass;
use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\SortableListThemePass;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixValue;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MauticPatchesBundle extends PluginBundleBase
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // the downloaded translation pack gets a text wrong ("Voltar ao %alvo%"): core's loader is replaced by one that fixes it
        $container->addCompilerPass(new OverridingTranslationLoaderPass());

        // the campaign scheduler gets the wait with a date per contact ("Wait", patches.wait_relative)
        $container->addCompilerPass(new RelativeWaitPass());

        // the add button of core's lists of values (field options, form fields, ...) comes below the list
        $container->addCompilerPass(new SortableListThemePass());
    }

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
        // The campaign prefix table, added in 0.5.0.
        if (!empty($metadata[CampaignPrefixValue::class]) && (null === $installedSchema || !$installedSchema->hasTable(CampaignPrefixValue::TABLE_NAME))) {
            self::installPluginSchema([$metadata[CampaignPrefixValue::class]], $factory);
        }
    }
}
