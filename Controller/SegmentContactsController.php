<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Controller\ListController;
use Mautic\LeadBundle\Security\Permissions\LeadPermissions;
use Symfony\Component\HttpFoundation\Request;

/**
 * The contacts tab of a segment's page as the contact list table (see ContactsTableTrait). It takes over core's route
 * `mautic_segment_contacts` (same path, same name, so the tab's data-target-url, the pagination and the filter form
 * keep working): the plugin's routes load after core's and a route name is unique, so this one wins. The filter
 * logic is a copy of ListController::contactsAction.
 */
class SegmentContactsController extends ListController
{
    use ContactsTableTrait;

    /**
     * @param int $objectId
     * @param int $page
     */
    public function contactsAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $objectId, $page = 1)
    {
        $session = $request->getSession();
        $session->set('mautic.segment.contact.page', $page);

        $contactFilter = ['manually_removed' => 0];
        if ('POST' === $request->getMethod() && $request->request->has('includeEvents')) {
            $session->set('mautic.segment.filters', ['includeEvents' => InputHelper::clean($request->get('includeEvents', []))]);
            $filters = $session->get('mautic.segment.filters');
        } else {
            $filters = [];
        }

        if (!empty($filters['includeEvents'])) {
            if (in_array('manually_added', $filters['includeEvents'])) {
                $contactFilter['manually_added'] = 1;
            }
            if (in_array('manually_removed', $filters['includeEvents'])) {
                $contactFilter['manually_removed'] = 1;
            }
            if (in_array('filter_added', $filters['includeEvents'])) {
                $contactFilter['manually_added'] = 0;
            }
        }

        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'      => (int) $objectId,
            'page'          => (int) $page,
            'sessionVar'    => 'segment',
            'route'         => ListController::ROUTE_SEGMENT_CONTACTS,
            'container'     => '#contacts-container',
            'permission'    => LeadPermissions::LISTS_VIEW,
            'joinTable'     => 'lead_lists_leads',
            'idColumn'      => 'leadlist_id',
            'contactFilter' => $contactFilter,
            'orderBy'       => 'l.id',
        ]);
    }
}
