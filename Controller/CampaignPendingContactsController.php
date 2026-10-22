<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The contacts waiting at one event of a campaign (the list the hourglass of a node opens, see CampaignEventPendingController)
 * as the contact list table (see ContactsTableTrait), the same one as the contacts tab of the campaign. The contacts are the
 * ones core counts on the hourglass: a log of the event that is still scheduled, of a member of the campaign. The earliest
 * scheduled first, until a column is clicked.
 */
class CampaignPendingContactsController extends CampaignController
{
    use ContactsTableTrait;

    /**
     * @param string|int $objectId the event's id
     * @param int        $page
     */
    public function pendingContactsAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $objectId, $page = 1)
    {
        $event = $this->getModel('campaign')->getEventRepository()->find((int) $objectId);

        if (null === $event || !$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $event->getCampaign()->getCreatedBy())) {
            return null === $event ? $this->notFound() : $this->accessDenied();
        }

        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'        => (int) $objectId,
            'page'            => (int) $page,
            'sessionVar'      => 'campaignPending',
            'route'           => 'mautic_patches_campaign_event_pending_contacts',
            'container'       => '#patches-pending-container',
            'permission'      => ['lead:leads:viewown', 'lead:leads:viewother'],
            'joinTable'       => 'campaign_lead_event_log',
            'idColumn'        => 'event_id',
            'contactFilter'   => ['is_scheduled' => 1],
            'orderBy'         => 'entity.trigger_date',
            'orderByDir'      => 'ASC',
            'additionalJoins' => [[
                'type'       => 'innerJoin',
                'from_alias' => 'entity',
                'table'      => 'campaign_leads',
                'alias'      => 'member',
                'condition'  => 'member.campaign_id = entity.campaign_id AND member.lead_id = entity.lead_id',
            ]],
        ]);
    }
}
