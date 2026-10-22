<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitRecalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two endpoints of the "Recalculate dates" button in the modal of a Wait node (Preview tab of the campaign page):
 *  - GET  /s/campaign-wait/{eventId}/recalculate/simulate: what the current rule would do to the contacts that wait (reads only);
 *  - POST /s/campaign-wait/{eventId}/recalculate/apply:    writes those dates.
 * The simulation has the access rule of the modal (view the campaign); applying needs the campaign's edit permission. Only a Wait
 * node (patches.wait_relative) has this: any other event is a 404.
 */
class CampaignWaitRecalculateController extends CommonController
{
    use CampaignEventAccessTrait;

    public function simulateAction(RelativeWaitRecalculator $recalculator, int $eventId): Response
    {
        $event = $this->waitEventOrDeny($eventId);

        if ($event instanceof Response) {
            return $event;
        }

        $summary = $recalculator->plan($event)['summary'];

        return new JsonResponse([
            'summary'  => $summary,
            'canApply' => $this->canEditCampaign($event) && $summary['changed'] + $summary['leaving_now'] > 0,
        ]);
    }

    public function applyAction(RelativeWaitRecalculator $recalculator, int $eventId): Response
    {
        $event = $this->waitEventOrDeny($eventId);

        if ($event instanceof Response) {
            return $event;
        }

        if (!$this->canEditCampaign($event)) {
            return $this->accessDenied();
        }

        $plan = $recalculator->plan($event);

        return new JsonResponse(['applied' => $recalculator->apply($plan), 'summary' => $plan['summary']]);
    }

    private function waitEventOrDeny(int $eventId): Event|Response
    {
        $event = $this->eventOrDeny($eventId);

        if ($event instanceof Event && RelativeWaitSubscriber::TYPE !== $event->getType()) {
            return $this->notFound();
        }

        return $event;
    }
}
