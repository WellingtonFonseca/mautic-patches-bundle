<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A plugin cannot name a form theme, and the form of the plugin settings modal is core's: the theme of the FeatureSwitchType row is
 * added to the default form themes of Twig. Its blocks only apply to that type.
 */
class FeatureSwitchThemePass implements CompilerPassInterface
{
    private const THEME = '@MauticPatches/FormTheme/feature_switch.html.twig';

    public function process(ContainerBuilder $container): void
    {
        $themes = $container->hasParameter('twig.form.resources') ? $container->getParameter('twig.form.resources') : [];
        $container->setParameter('twig.form.resources', array_values(array_unique([...$themes, self::THEME])));
    }
}
