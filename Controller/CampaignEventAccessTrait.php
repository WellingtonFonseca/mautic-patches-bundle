<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Entity\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * The rule of the campaign node modals about the event in their URL, in one place for the controller of the modal's body
 * (CampaignEventPendingController) and the one of its table (CampaignPendingContactsController): an unknown event, or one
 * without a campaign, is a 404; a user who cannot view the campaign (own / other) gets a 403. Needs a core controller
 * (getModel, security, notFound, accessDenied).
 */
trait CampaignEventAccessTrait
{
    /**
     * @return Event|Response the event, or the response to send back
     */
    private function eventOrDeny(int $eventId)
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
