<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignEvent;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Safety net of RelativeWaitNameExtension: before a campaign is saved, each Wait event gets the name its amount and unit give
 * (covers the API, the MCP and imports, which do not go through the node form).
 */
class RelativeWaitNameSubscriber implements EventSubscriberInterface
{
    public function __construct(private RelativeWaitNameFormatter $formatter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CampaignEvents::CAMPAIGN_PRE_SAVE => ['onPreSave', 0]];
    }

    public function onPreSave(CampaignEvent $event): void
    {
        foreach ($event->getCampaign()->getEvents() as $wait) {
            if (RelativeWaitSubscriber::isWait($wait)) {
                $wait->setName($this->formatter->fromProperties($wait->getProperties()));
            }
        }
    }
}
