<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Security\Permissions\LeadPermissions;
use Mautic\LeadBundle\Segment\Stat\SegmentCampaignShare;
use MauticPlugin\MauticPatchesBundle\Service\SegmentFilterPresenter;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/segment-details/{id}: the body of the details modal of the segment list (opened from the segment's name): the
 * tabs of the segment page (Details, Filters, Contacts, Campaign share) and, in the footer, Close, Details (the page)
 * and Edit. Same access rule as core's segment page (own / other, or a global segment); Edit only for who may edit.
 * The Contacts tab and the campaign shares are loaded when their tab is first shown (SegmentListClickSubscriber's script).
 */
class SegmentDetailsController extends CommonController
{
    public function detailsAction(ListModel $listModel, SegmentFilterPresenter $presenter, SegmentCampaignShare $campaignShare, int $id): Response
    {
        $segment = $listModel->getEntity($id);

        if (null === $segment) {
            return $this->notFound();
        }

        if (!$segment->isGlobal() && !$this->security->hasEntityAccess(LeadPermissions::LISTS_VIEW_OWN, LeadPermissions::LISTS_VIEW_OTHER, $segment->getCreatedBy())) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'viewParameters' => [
                'list'          => $segment,
                'segmentCount'  => $listModel->getRepository()->getLeadCount($segment->getId()),
                'rows'          => $presenter->present($segment->getFilters(), $listModel->getChoiceFields()),
                'campaignStats' => $campaignShare->getCampaignList($segment->getId()),
                'canEdit'       => $this->security->hasEntityAccess(LeadPermissions::LISTS_EDIT_OWN, LeadPermissions::LISTS_EDIT_OTHER, $segment->getCreatedBy()),
            ],
            'contentTemplate' => '@MauticPatches/Segment/details_modal.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }
}
