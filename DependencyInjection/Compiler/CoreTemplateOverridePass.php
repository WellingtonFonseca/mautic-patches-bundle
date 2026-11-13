<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Puts a plugin folder first in core's "MauticCampaign" Twig namespace, so a template there wins over core's.
 * The folder holds only wrappers: each one includes core's original through "@!MauticCampaign" and changes its output.
 */
class CoreTemplateOverridePass implements CompilerPassInterface
{
    public const NAMESPACE = 'MauticCampaign';

    public const FOLDER = __DIR__.'/../../Resources/views/CoreOverride/MauticCampaign';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }

        $container->getDefinition('twig.loader.native_filesystem')
            ->addMethodCall('prependPath', [(string) realpath(self::FOLDER), self::NAMESPACE]);
    }
}
