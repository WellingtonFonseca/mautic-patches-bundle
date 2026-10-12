<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentListRepository;
use MauticPlugin\MauticPatchesBundle\Doctrine\SegmentRepositoryListener;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('MauticPlugin\\MauticPatchesBundle\\', '../')
        ->exclude('../{'.implode(',', MauticCoreExtension::DEFAULT_EXCLUDES).'}');

    // The segment entity gets the repository that sorts by prefix (SegmentListRepository).
    $services->set(SegmentRepositoryListener::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    // Core's repositories are services (the entity manager asks the container for them). Its entity cannot be guessed
    // from this class name, so it is given.
    $services->set(SegmentListRepository::class)
        ->args([service('doctrine'), LeadList::class])
        ->tag('doctrine.repository_service');

    // A plain string argument cannot be autowired.
    $services->set(SegmentRebuildLauncher::class)
        ->args(['%kernel.project_dir%', null]);

    // The day files of the API timing, next to Mautic's own logs.
    $services->set(PerformanceLog::class)
        ->args(['%kernel.logs_dir%/performance']);
};
