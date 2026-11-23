<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentCampaignDetailStatusSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEditPermission;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/campaign-details/{id}: the body of the details modal of the campaign list (opened from the campaign's name): the header
 * component and the tabs of the campaign page (Preview, Etapas) and, in the footer, Edit, Details (the page) and Close.
 * It extends core's CampaignController only to reuse its getViewArguments('view'): the very data of the campaign page (events with their counts,
 * sources, settings), so the preview and the Etapas tab are the page's own. Same access rule as core's page (view own / other).
 */
class CampaignDetailsController extends CampaignController
{
    public function detailsAction(SegmentCampaignDetailStatusSubscriber $head, CampaignEditPermission $editPermission, int $id): Response
    {
        $campaign = $this->getCampaignModel()->getEntity($id);

        if (null === $campaign) {
            return $this->notFound();
        }

        if (!$this->checkActionPermission('view', $campaign)) {
            return $this->accessDenied();
        }

        $args = $this->getViewArguments(['entity' => $campaign, 'objectId' => $id, 'viewParameters' => []], 'view');

        return $this->delegateView([
            'viewParameters' => $args['viewParameters'] + [
                'headHtml' => $head->campaignModalHeadHtml($campaign),
                'canEdit'  => $editPermission->allows($campaign),
            ],
            'contentTemplate' => '@MauticPatches/Campaign/details_modal.html.twig',
            // mauticContent: core then runs campaignOnLoad on the modal, which draws the preview canvas
            'passthroughVars' => ['route' => false, 'mauticContent' => 'campaign'],
        ]);
    }
}
