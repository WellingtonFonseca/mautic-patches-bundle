<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\Model\CampaignModel;
use MauticPlugin\MauticPatchesBundle\Model\CampaignPrefixModel;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixRepository;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentListRepository;
use MauticPlugin\MauticPatchesBundle\Doctrine\CampaignRepositoryListener;
use MauticPlugin\MauticPatchesBundle\Doctrine\SegmentRepositoryListener;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\EventListener\TimeInputFormatSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuildLauncher;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Segment\SegmentItemResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;
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
        ->exclude('../{'.implode(',', MauticCoreExtension::DEFAULT_EXCLUDES).',Loader}');

    // The segment entity gets the repository that sorts by prefix (SegmentListRepository).
    $services->set(SegmentRepositoryListener::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    // Core's repositories are services (the entity manager asks the container for them). Its entity cannot be guessed
    // from this class name, so it is given.
    $services->set(SegmentListRepository::class)
        ->args([service('doctrine'), LeadList::class])
        ->tag('doctrine.repository_service');
    // Mautic 7: the models get their repository injected by its class (core's service), not from the entity manager,
    // so core's service id is made an instance of ours too, or the list would sort with core's repository.
    $services->set(LeadListRepository::class, SegmentListRepository::class)
        ->args([service('doctrine'), LeadList::class])
        ->tag('doctrine.repository_service');

    // The same for the campaign entity (CampaignPrefixRepository, sorting by prefix).
    $services->set(CampaignRepositoryListener::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    $services->set(CampaignPrefixRepository::class)
        ->args([service('doctrine'), Campaign::class])
        ->tag('doctrine.repository_service');
    $services->set(CampaignRepository::class, CampaignPrefixRepository::class)
        ->args([service('doctrine'), Campaign::class])
        ->tag('doctrine.repository_service');

    // The campaign model names the segments of the contact source "PREFIX - Name": core's service id becomes ours.
    $services->set(CampaignModel::class, CampaignPrefixModel::class)
        ->autowire()
        ->autoconfigure()
        ->public();

    // Core's relative date service has the id `mautic.lead.model.relative_date`, not its class name, so it is not autowired (the translator
    // gives the same keywords in English).
    $services->set(TimeInputFormatSubscriber::class)
        ->args([service('mautic.lead.model.relative_date'), service('translator')]);

    // A plain string argument cannot be autowired.
    $services->set(SegmentRebuildLauncher::class)
        ->args(['%kernel.project_dir%', null]);

    // The marker files of the running campaign updates go to a folder in Mautic's logs dir (writable by the web user).
    $services->set(CampaignRebuildLauncher::class)
        ->args(['%kernel.project_dir%', '%kernel.logs_dir%/campaign-rebuild', null]);

    // The date fields of a "Date" are days of the system time zone (a string argument cannot be autowired).
    $services->set(RelativeWaitDateResolver::class)
        ->arg('$timezone', '%mautic.default_timezone%');

    // The Custom Objects plugin is optional: without it there are no items to select (null arguments).
    $services->set(RelativeWaitItemSelector::class)
        ->arg('$itemResolver', service(SegmentItemResolver::class)->nullOnInvalid())
        ->arg('$customObjectModel', service(CustomObjectModel::class)->nullOnInvalid());

    // The day files of the API timing, next to Mautic's own logs.
    $services->set(PerformanceLog::class)
        ->args(['%kernel.logs_dir%/performance']);
};
