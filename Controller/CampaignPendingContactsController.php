<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The contacts of one event of a campaign (the lists the hourglass and the check of a node open, see
 * CampaignEventPendingController) as the contact list table (see ContactsTableTrait), the same one as the contacts tab of the
 * campaign. The contacts are the ones core counts on the node: a log of the event that is still scheduled (hourglass, the
 * earliest scheduled first) or already run (check, the latest first), of a member of the campaign, not failed; until a column
 * is clicked.
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
        return $this->eventContacts($request, $pageHelperFactory, (int) $objectId, (int) $page, [
            'sessionVar' => 'campaignPending',
            'route'      => 'mautic_patches_campaign_event_pending_contacts',
            'scheduled'  => 1,
            'orderBy'    => 'entity.trigger_date',
            'orderByDir' => 'ASC',
        ]);
    }

    /**
     * @param string|int $objectId the event's id
     * @param int        $page
     */
    public function doneContactsAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $objectId, $page = 1)
    {
        return $this->eventContacts($request, $pageHelperFactory, (int) $objectId, (int) $page, [
            'sessionVar' => 'campaignDone',
            'route'      => 'mautic_patches_campaign_event_done_contacts',
            'scheduled'  => 0,
            'orderBy'    => 'entity.date_triggered',
            'orderByDir' => 'DESC',
        ]);
    }

    /**
     * @param array{sessionVar: string, route: string, scheduled: int, orderBy: string, orderByDir: string} $list
     */
    private function eventContacts(Request $request, PageHelperFactoryInterface $pageHelperFactory, int $eventId, int $page, array $list)
    {
        $event = $this->getModel('campaign')->getEventRepository()->find($eventId);

        if (null === $event || !$this->security->hasEntityAccess('campaign:campaigns:viewown', 'campaign:campaigns:viewother', $event->getCampaign()->getCreatedBy())) {
            return null === $event ? $this->notFound() : $this->accessDenied();
        }

        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'        => $eventId,
            'page'            => $page,
            'sessionVar'      => $list['sessionVar'],
            'route'           => $list['route'],
            'container'       => '#patches-pending-container',
            'permission'      => ['lead:leads:viewown', 'lead:leads:viewother'],
            'joinTable'       => 'campaign_lead_event_log',
            'idColumn'        => 'event_id',
            // a log that failed is in neither count of core
            'contactFilter'   => ['is_scheduled' => $list['scheduled'], ['expr' => 'isNull', 'col' => 'failed.log_id']],
            'orderBy'         => $list['orderBy'],
            'orderByDir'      => $list['orderByDir'],
            'additionalJoins' => [
                [
                    'type'       => 'innerJoin',
                    'from_alias' => 'entity',
                    'table'      => 'campaign_leads',
                    'alias'      => 'member',
                    'condition'  => 'member.campaign_id = entity.campaign_id AND member.lead_id = entity.lead_id',
                ],
                [
                    'type'       => 'leftJoin',
                    'from_alias' => 'entity',
                    'table'      => 'campaign_lead_event_failed_log',
                    'alias'      => 'failed',
                    'condition'  => 'failed.log_id = entity.id',
                ],
            ],
        ]);
    }
}
