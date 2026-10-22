<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The contacts waiting at one event of a campaign (the list the hourglass of a node opens, see CampaignEventPendingController) as
 * the contact list table (see ContactsTableTrait), the same one as the contacts tab of the campaign. The contacts behind the
 * number of the node: a member of the campaign with a log of the event that is still scheduled, not failed, ONCE each however many
 * logs (rotations) it has. The number itself is core's and counts LOGS, so after a restart it can be larger than this list. The
 * latest to join the campaign first, until a column is clicked.
 */
class CampaignPendingContactsController extends CampaignController
{
    use CampaignEventAccessTrait;
    use ContactsTableTrait;

    /**
     * @param string|int $objectId the event's id
     * @param int        $page
     */
    public function pendingContactsAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $objectId, $page = 1)
    {
        $event = $this->eventOrDeny((int) $objectId);

        if ($event instanceof Response) {
            return $event;
        }

        return $this->contactsTable($request, $pageHelperFactory, [
            // One row per CONTACT of the campaign (campaign_leads has one), not per log: a campaign that restarts gives a contact
            // a log of the same event for each rotation, and a join with the log table counted (and paged) the contact once per log.
            'entityId'        => (int) $event->getCampaign()->getId(),
            'routeId'         => (int) $objectId,
            'page'            => (int) $page,
            'sessionVar'      => 'campaignPending',
            'route'           => 'mautic_patches_campaign_event_pending_contacts',
            'container'       => '#patches-pending-container',
            // the campaign's own view permission, as core's contacts tab of a campaign (EntityContactsTrait) checks: isGranted() wants ALL the
            // permissions of an array, so ['lead:leads:viewown', 'lead:leads:viewother'] left a role with only one of them an empty table
            'permission'      => 'campaign:campaigns:view',
            'joinTable'       => 'campaign_leads',
            'idColumn'        => 'campaign_id',
            // the contact has a log of this event that is still scheduled, and not a failed one, on either path (core counts both)
            'contactFilter'   => [[
                'expr'     => 'formula',
                'internal' => true,
                'val'      => sprintf(
                    'EXISTS (SELECT 1 FROM %1$scampaign_lead_event_log evlog WHERE evlog.lead_id = entity.lead_id AND evlog.event_id = %2$d AND evlog.is_scheduled = 1'
                    .' AND NOT EXISTS (SELECT 1 FROM %1$scampaign_lead_event_failed_log failed WHERE failed.log_id = evlog.id))',
                    MAUTIC_TABLE_PREFIX,
                    (int) $objectId
                ),
            ]],
            'orderBy'         => 'entity.date_added',
            'orderByDir'      => 'DESC',
        ]);
    }
}
