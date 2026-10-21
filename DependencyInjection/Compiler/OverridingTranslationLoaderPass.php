<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\Loader\OverridingTranslationLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Core's translation loader is defined by core's own config and is a final class on this version: it cannot be extended. Once everything
 * is read, the definition of its service id is made OverridingTranslationLoader, which wraps a private copy of core's own definition, so the
 * texts of Resources/translation-overrides go over core's catalogue. The tag and the alias stay on the service id.
 */
class OverridingTranslationLoaderPass implements CompilerPassInterface
{
    public const CORE_COPY = 'mautic_patches.translation_loader.core';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mautic.translation.loader') || $container->hasDefinition(self::CORE_COPY)) {
            return;
        }

        $core = $container->getDefinition('mautic.translation.loader');
        $copy = clone $core;
        $copy->clearTags();
        $copy->setPublic(false);
        $container->setDefinition(self::CORE_COPY, $copy);

        $core->setClass(OverridingTranslationLoader::class)->setArguments([new Reference(self::CORE_COPY)]);
    }
}
