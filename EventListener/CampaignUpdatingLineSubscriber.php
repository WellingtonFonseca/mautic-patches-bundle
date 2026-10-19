<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts an empty line under the name of each campaign in the campaign list (core's
 * customContent('campaign.name') hook). CampaignUpdateLockSubscriber's script fills
 * it with "<spinner> Updating..." while that campaign's update runs.
 */
class CampaignUpdatingLineSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        $campaign = $customContentEvent->getVars()['item'] ?? null;

        if ('campaign.name' !== $customContentEvent->getContext() || !$campaign instanceof Campaign) {
            return;
        }

        $customContentEvent->addTemplate('@MauticPatches/Campaign/updating_line.html.twig', ['item' => $campaign]);
    }
}
