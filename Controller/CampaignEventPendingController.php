<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/campaign-pending/{eventId} and /s/campaign-done/{eventId}: the body of the modal the hourglass (pending) and the check
 * (done) of a campaign node open (Preview tab): the search box and the container of the contacts waiting at / done with that event. The table
 * itself is loaded when the modal opens (CampaignPendingClickSubscriber's script, from CampaignPendingContactsController), so
 * the modal opens at once. Same access rule as the campaign page (view own / other).
 */
class CampaignEventPendingController extends CommonController
{
    public function pendingAction(EntityManagerInterface $entityManager, int $eventId): Response
    {
        return $this->show($entityManager, $eventId, 'mautic_patches_campaign_event_pending_contacts', 'campaignPending');
    }

    public function doneAction(EntityManagerInterface $entityManager, int $eventId): Response
    {
        return $this->show($entityManager, $eventId, 'mautic_patches_campaign_event_done_contacts', 'campaignDone');
    }

    private function show(EntityManagerInterface $entityManager, int $eventId, string $tableRoute, string $sessionVar): Response
    {
        $event = $entityManager->getRepository(Event::class)->find($eventId);

        if (null === $event || null === $event->getCampaign()) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $event->getCampaign()->getCreatedBy())) {
            return $this->accessDenied();
        }

        // The table keeps its search in the session (ContactsTableTrait): the modal opens with an empty search box, so it opens
        // with an empty search too.
        $this->getCurrentRequest()->getSession()->set('mautic.'.$sessionVar.'.contact.filter', '');

        return $this->delegateView([
            'viewParameters'  => ['event' => $event, 'tableRoute' => $tableRoute],
            'contentTemplate' => '@MauticPatches/Campaign/pending_modal.html.twig',
            'passthroughVars' => ['route' => false, 'mauticContent' => 'campaignPending'],
        ]);
    }
}
