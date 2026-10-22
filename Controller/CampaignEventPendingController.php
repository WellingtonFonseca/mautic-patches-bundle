<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEventPendingContacts;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/campaign-pending/{eventId}: the body of the modal the hourglass of a campaign node opens (Preview tab):
 * the contacts waiting at that event, the first LIMIT of them, with the date each is scheduled for. Same access rule as the
 * campaign page (view own / other) plus the right to see contacts.
 */
class CampaignEventPendingController extends CommonController
{
    private const LIMIT = 50;

    public function pendingAction(EntityManagerInterface $entityManager, CampaignEventPendingContacts $pending, int $eventId): Response
    {
        $event = $entityManager->getRepository(Event::class)->find($eventId);

        if (null === $event || null === $event->getCampaign()) {
            return $this->notFound();
        }

        $campaign = $event->getCampaign();

        if (!$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $campaign->getCreatedBy())
            || !$this->security->isGranted(['lead:leads:viewown', 'lead:leads:viewother'], 'MATCH_ONE')) {
            return $this->accessDenied();
        }

        $found = $pending->find($eventId, (int) $campaign->getId(), self::LIMIT);

        return $this->delegateView([
            'viewParameters' => [
                'event'   => $event,
                'total'   => $found['total'],
                'rows'    => $found['rows'],
                'limit'   => self::LIMIT,
            ],
            'contentTemplate' => '@MauticPatches/Campaign/pending_modal.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }
}
