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
 * Core's campaign scheduler is a service of core's own config array (read after this plugin's services.php, so a definition here
 * would be replaced): once everything is read, its class becomes RelativeWaitScheduler, which extends it. Its arguments stay
 * core's; the resolver of the dates and the selector of the items come by setters.
 */
class RelativeWaitPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mautic.campaign.scheduler')) {
            return;
        }

        $container->getDefinition('mautic.campaign.scheduler')
            ->setClass(RelativeWaitScheduler::class)
            ->addMethodCall('setRelativeWaitDateResolver', [new Reference(RelativeWaitDateResolver::class)])
            ->addMethodCall('setRelativeWaitItemSelector', [new Reference(RelativeWaitItemSelector::class)]);
    }
}
