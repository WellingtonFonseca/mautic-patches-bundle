<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/campaign-pending/{eventId}: the body of the modal the hourglass (pending count) of a campaign node opens (Preview tab): the
 * container of the contacts waiting at that event. The table itself (with its search bar) is loaded when the modal opens
 * (CampaignPendingClickSubscriber's script, from CampaignPendingContactsController), so the modal opens at once. Same access rule
 * as the campaign page (view own / other).
 */
class CampaignEventPendingController extends CommonController
{
    use CampaignEventAccessTrait;

    public function pendingAction(int $eventId): Response
    {
        $event = $this->eventOrDeny($eventId);

        if ($event instanceof Response) {
            return $event;
        }

        return $this->delegateView([
            'viewParameters'  => ['event' => $event],
            'contentTemplate' => '@MauticPatches/Campaign/pending_modal.html.twig',
            'passthroughVars' => ['route' => false, 'mauticContent' => 'campaignPending'],
        ]);
    }
}
