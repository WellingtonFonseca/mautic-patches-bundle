<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Entity\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * The rule about the campaign event in a URL, in one place for the table of the second "Ações" tab (CampaignActionContactsController)
 * and the "Recalculate dates" routes (CampaignWaitRecalculateController): an unknown event, or one
 * without a campaign, is a 404; a user who cannot view the campaign (own / other) gets a 403. Needs a core controller
 * (getModel, security, notFound, accessDenied).
 */
trait CampaignEventAccessTrait
{
    /**
     * The event, or the response to send back.
     */
    private function eventOrDeny(int $eventId): Event|Response
    {
        $event = $this->findEvent($eventId);

        if (null === $event || null === $event->getCampaign()) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $event->getCampaign()->getCreatedBy())) {
            return $this->accessDenied();
        }

        return $event;
    }

    protected function findEvent(int $eventId): ?Event
    {
        return $this->getModel('campaign')->getEventRepository()->find($eventId);
    }
}
