<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionContactSql;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one table of contacts under the second "Ações" tab of the campaign page (wiki/patches-campaign-actions-tab.md): the failed
 * (retries included) or the waiting contacts of ONE action step, as the contact list table (ContactsTableTrait). The buttons of the
 * step's badges load it; before a click it is loaded empty (noneAction). One route per state, because the table's sorting and paging post to the route of its first load.
 */
class CampaignActionContactsController extends CampaignController
{
    use CampaignEventAccessTrait;
    use ContactsTableTrait;

    /**
     * @param string|int $objectId the event's id
     * @param int        $page
     */
    public function failedAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, CampaignActionContactSql $sql, $objectId, $page = 1)
    {
        return $this->table($request, $pageHelperFactory, (int) $objectId, (int) $page, 'failed', fn (int $eventId): string => $sql->failed($eventId));
    }

    /**
     * @param string|int $objectId the event's id
     * @param int        $page
     */
    public function waitingAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, CampaignActionContactSql $sql, $objectId, $page = 1)
    {
        return $this->table($request, $pageHelperFactory, (int) $objectId, (int) $page, 'waiting', fn (int $eventId): string => $sql->waiting($eventId));
    }

    /**
     * The table with no contact: what the tab shows before a button is clicked (the columns and the search bar, no rows).
     *
     * @param string|int $objectId the event's id (any action step of the campaign: the access rule needs one)
     * @param int        $page
     */
    public function noneAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $objectId, $page = 1)
    {
        return $this->table($request, $pageHelperFactory, (int) $objectId, (int) $page, 'none', static fn (): string => '1 = 0');
    }

    /**
     * @param callable(int): string $condition
     */
    private function table(Request $request, PageHelperFactoryInterface $pageHelperFactory, int $eventId, int $page, string $state, callable $condition): Response
    {
        $event = $this->eventOrDeny($eventId);

        if ($event instanceof Response) {
            return $event;
        }

        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'      => (int) $event->getCampaign()->getId(),
            'routeId'       => $eventId,
            'page'          => $page,
            'sessionVar'    => 'campaignAction'.ucfirst($state),
            'route'         => 'mautic_patches_campaign_action_'.$state.'_contacts',
            'container'     => '#patches-actions-table',
            // the campaign's own view permission, as the contacts tab of core checks
            'permission'    => 'campaign:campaigns:view',
            'joinTable'     => 'campaign_leads',
            'idColumn'      => 'campaign_id',
            'contactFilter' => [['expr' => 'formula', 'internal' => true, 'val' => $condition($eventId)]],
            'orderBy'       => 'entity.date_added',
            'orderByDir'    => 'DESC',
        ]);
    }
}
