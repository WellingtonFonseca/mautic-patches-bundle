<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\Executioner\RelativeWaitScheduler;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Core's campaign scheduler (on Mautic 7 the service of its class, `mautic.campaign.scheduler` is an alias of it; the 5 has the id as the definition) is a service of core's own config (read after this plugin's services.php, so a definition here
 * would be replaced): once everything is read, its class becomes RelativeWaitScheduler, which extends it. Its arguments stay
 * core's; the resolver of the dates and the selector of the items come by setters.
 */
class RelativeWaitPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // On Mautic 7 `mautic.campaign.scheduler` is only an ALIAS of the class's own service (there is no definition with that id): the definition
        // to change is the one the alias points at. On Mautic 5 the id is the definition.
        $id = $container->hasAlias('mautic.campaign.scheduler') ? (string) $container->getAlias('mautic.campaign.scheduler') : 'mautic.campaign.scheduler';
        if (!$container->hasDefinition($id)) {
            return;
        }

        $container->getDefinition($id)
            ->setClass(RelativeWaitScheduler::class)
            ->addMethodCall('setRelativeWaitDateResolver', [new Reference(RelativeWaitDateResolver::class)])
            ->addMethodCall('setRelativeWaitItemSelector', [new Reference(RelativeWaitItemSelector::class)]);
    }
}
