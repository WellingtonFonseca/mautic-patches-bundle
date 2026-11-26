<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Security\Permissions\LeadPermissions;
use MauticPlugin\MauticPatchesBundle\Service\SegmentSettingsColumn;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/segment-settings/{id}: the read-only right column (Configurações + Distinct) of the segment page, as an HTML fragment that
 * SegmentDetailsTabSubscriber's script puts in the page's right column. Same access rule as core's segment page.
 */
class SegmentSettingsController extends CommonController
{
    public function settingsAction(ListModel $listModel, SegmentSettingsColumn $column, int $id): Response
    {
        $segment = $listModel->getEntity($id);

        if (null === $segment) {
            return $this->notFound();
        }

        if (!$segment->isGlobal() && !$this->security->hasEntityAccess(LeadPermissions::LISTS_VIEW_OWN, LeadPermissions::LISTS_VIEW_OTHER, $segment->getCreatedBy())) {
            return $this->accessDenied();
        }

        return new Response($column->html($segment));
    }
}
