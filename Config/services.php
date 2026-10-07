<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('MauticPlugin\\MauticPatchesBundle\\', '../')
        ->exclude('../{'.implode(',', MauticCoreExtension::DEFAULT_EXCLUDES).'}');

    // A plain string argument cannot be autowired.
    $services->set(SegmentRebuildLauncher::class)
        ->args(['%kernel.project_dir%', null]);

    // The day files of the API timing, next to Mautic's own logs.
    $services->set(PerformanceLog::class)
        ->args(['%kernel.logs_dir%/performance']);
};
