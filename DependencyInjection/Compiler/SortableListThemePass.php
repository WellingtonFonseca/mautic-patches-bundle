<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Core's list of values (`sortablelist_row` of its form theme) has the add button ABOVE the list. Twig takes the block of the LAST form
 * theme that defines it, and core's theme is set by its own config (a parameter read after this plugin's services.php): once everything
 * is read, Resources/views/FormTheme/sortablelist.html.twig is added to the end of `twig.form.resources`, with the button below the list.
 * Forms with a block of their own (the field options of the Custom Objects plugin) are not touched.
 */
class SortableListThemePass implements CompilerPassInterface
{
    public const THEME = '@MauticPatches/FormTheme/sortablelist.html.twig';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('twig.form.resources')) {
            return;
        }

        $themes = $container->getParameter('twig.form.resources');
        if (!is_array($themes) || in_array(self::THEME, $themes, true)) {
            return;
        }

        $container->setParameter('twig.form.resources', [...$themes, self::THEME]);
    }
}
