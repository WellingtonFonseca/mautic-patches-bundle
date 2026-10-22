<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/campaign-pending/{eventId}: the body of the modal the hourglass of a campaign node opens (Preview tab): the container of
 * the contacts waiting there. The table itself is loaded when the modal opens
 * (CampaignPendingClickSubscriber's script, from CampaignPendingContactsController), so the modal opens at once. Same
 * access rule as the campaign page (view own / other).
 */
class CampaignEventPendingController extends CommonController
{
    public function pendingAction(EntityManagerInterface $entityManager, int $eventId): Response
    {
        $event = $entityManager->getRepository(Event::class)->find($eventId);

        if (null === $event || null === $event->getCampaign()) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $event->getCampaign()->getCreatedBy())) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'viewParameters'  => ['event' => $event],
            'contentTemplate' => '@MauticPatches/Campaign/pending_modal.html.twig',
            'passthroughVars' => ['route' => false, 'mauticContent' => 'campaignPending'],
        ]);
    }
}
