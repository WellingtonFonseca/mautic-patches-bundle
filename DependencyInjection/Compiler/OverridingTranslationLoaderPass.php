<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\Loader\OverridingTranslationLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Core's translation loader is defined by core's own config (a service id of its array, read after this plugin's services.php, so a
 * definition there would be replaced): once everything is read, its class becomes OverridingTranslationLoader, which puts the texts of
 * Resources/translation-overrides over core's catalogue. Its arguments, tag and alias stay core's.
 */
class OverridingTranslationLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('mautic.translation.loader')) {
            $container->getDefinition('mautic.translation.loader')->setClass(OverridingTranslationLoader::class);
        }
    }
}
