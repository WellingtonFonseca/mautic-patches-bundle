<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Event\CampaignEvent;
use Mautic\CampaignBundle\CampaignEvents;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the prefix submitted with the campaign form (or the API) once the campaign is saved and has its id, and drops
 * it when the campaign is deleted.
 */
class CampaignPrefixSaveSubscriber implements EventSubscriberInterface
{
    public function __construct(private CampaignPrefixStore $store)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_POST_SAVE   => 'onPostSave',
            CampaignEvents::CAMPAIGN_POST_DELETE => 'onPostDelete',
        ];
    }

    public function onPostSave(CampaignEvent $event): void
    {
        $campaign = $event->getCampaign();
        $prefix  = $this->store->pending($campaign);

        if (null !== $prefix && null !== $campaign->getId()) {
            $this->store->save((int) $campaign->getId(), $prefix);
        }
    }

    public function onPostDelete(CampaignEvent $event): void
    {
        $campaign = $event->getCampaign();
        $id      = $campaign->getId() ?? ($campaign->deletedId ?? null);

        if (null !== $id) {
            $this->store->remove((int) $id);
        }
    }
}
