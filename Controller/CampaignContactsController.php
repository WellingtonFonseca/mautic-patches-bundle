<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The contacts tab of a campaign's page as the contact list table (see ContactsTableTrait). It takes over core's
 * route `mautic_campaign_contacts`, the same way SegmentContactsController does for the segment. Core sorts this tab
 * by entity.lead_id whatever the session says; here that is only the default, a click on a column sorts.
 */
class CampaignContactsController extends CampaignController
{
    use ContactsTableTrait;

    /**
     * @param string|int $objectId
     * @param int        $page
     */
    public function contactsAction(
        Request $request,
        PageHelperFactoryInterface $pageHelperFactory,
        $objectId,
        $page = 1,
        $count = null,
        \DateTimeInterface $dateFrom = null,
        \DateTimeInterface $dateTo = null
    ) {
        $request->getSession()->set('mautic.campaign.contact.page', $page);

        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'      => (int) $objectId,
            'page'          => (int) $page,
            'sessionVar'    => 'campaign',
            'route'         => 'mautic_campaign_contacts',
            'container'     => '#leads-container',
            'permission'    => ['campaign:campaigns:view', 'lead:leads:viewown', 'lead:leads:viewother'],
            'joinTable'     => 'campaign_leads',
            'idColumn'      => 'campaign_id',
            'contactFilter' => ['manually_removed' => 0],
            'orderBy'       => 'entity.lead_id',
        ]);
    }
}
