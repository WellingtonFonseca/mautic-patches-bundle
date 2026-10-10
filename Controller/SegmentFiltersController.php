<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Security\Permissions\LeadPermissions;
use MauticPlugin\MauticPatchesBundle\Service\SegmentFilterPresenter;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/segment-filters/{id}: the body of the "Filtros" tab of the segment page, a read-only table of the filters
 * the segment applies (without opening the edit form). Same access rule as core's segment page (own / other, or a
 * global segment). Answers an HTML fragment, which the tab's script puts in its pane.
 */
class SegmentFiltersController extends CommonController
{
    public function filtersAction(ListModel $listModel, SegmentFilterPresenter $presenter, int $id): Response
    {
        $segment = $listModel->getEntity($id);

        if (null === $segment) {
            return $this->notFound();
        }

        if (!$segment->isGlobal() && !$this->security->hasEntityAccess(LeadPermissions::LISTS_VIEW_OWN, LeadPermissions::LISTS_VIEW_OTHER, $segment->getCreatedBy())) {
            return $this->accessDenied();
        }

        return new Response($this->renderView('@MauticPatches/Segment/filters.html.twig', [
            'rows' => $presenter->present($segment->getFilters(), $listModel->getChoiceFields()),
        ]));
    }
}
