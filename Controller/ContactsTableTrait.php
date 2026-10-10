<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Services\ContactColumnsDictionary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The contacts tab of a segment / campaign page as the contact list TABLE (same columns, in the order set in
 * Configuration > Contact Settings) instead of core's cards. Used by SegmentContactsController and
 * CampaignContactsController, which take over core's routes of those tabs.
 *
 * The query is the one of core's EntityContactsTrait::generateContactsGrid with ONE difference: core limits the
 * select of the segment to id, company, city, state and country (what the cards show), so the custom fields of the
 * columns (inst_alias, ...) would be empty. A 'select' cannot name them either (it only accepts the columns
 * Doctrine maps, not the custom fields), so none is passed and the repository reads all fields, like the contact
 * list does. Needs the host controller to be a core controller (delegateView, security, setListFilters, ...).
 */
trait ContactsTableTrait
{
    private ContactColumnsDictionary $columnsDictionary;

    // A setter, so the long constructor of the core controller is not repeated here.
    #[Required]
    public function setColumnsDictionary(ContactColumnsDictionary $columnsDictionary): void
    {
        $this->columnsDictionary = $columnsDictionary;
    }

    /**
     * @param array{
     *     entityId: int, page: int, sessionVar: string, route: string, container: string,
     *     permission: string|string[], joinTable: string, idColumn: string, contactFilter: array<string, int>,
     *     orderBy: string
     * } $spec container = the selector of the tab pane the table is loaded into (the target of sorting/paging)
     */
    private function contactsTable(Request $request, PageHelperFactoryInterface $pageHelperFactory, array $spec)
    {
        $sessionVar = $spec['sessionVar'];
        $route      = $spec['route'];
        $entityId   = $spec['entityId'];
        $page       = $spec['page'];
        $template   = '@MauticPatches/Contact/contacts_table.html.twig';
        $view       = [
            'page'          => $page,
            'tmpl'          => $sessionVar.'Contacts',
            'baseUrl'       => $this->generateUrl($route, ['objectId' => $entityId]),
            'sessionVar'    => $sessionVar.'.contact',
            'container'     => $spec['container'],
            'noContactList' => [],
            'columns'       => [],
            'permissions'   => [],
        ];
        $pass = ['mauticContent' => $sessionVar.'Contacts', 'route' => false];

        if (!$this->security->isGranted($spec['permission'])) {
            return $this->delegateView(['viewParameters' => $view + ['items' => [], 'totalItems' => 0, 'limit' => 0], 'contentTemplate' => $template, 'passthroughVars' => $pass]);
        }

        if ('POST' === $request->getMethod()) {
            $this->setListFilters($sessionVar.'.contact');
        }

        $session = $request->getSession();
        $search  = $request->get('search', $session->get('mautic.'.$sessionVar.'.contact.filter', ''));
        $session->set('mautic.'.$sessionVar.'.contact.filter', $search);

        $pageHelper = $pageHelperFactory->make("mautic.{$sessionVar}", $page);
        $orderBy    = $session->get('mautic.'.$sessionVar.'.contact.orderby', $spec['orderBy']);
        $orderByDir = $session->get('mautic.'.$sessionVar.'.contact.orderbydir', 'DESC');
        $limit      = $session->get('mautic.'.$sessionVar.'.contact.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start      = max(0, (1 === $page) ? 0 : (($page - 1) * $limit));
        $columns    = $this->columnsDictionary->getColumns();

        /** @var LeadRepository $repo */
        $repo     = $this->getModel('lead')->getRepository();
        $contacts = $repo->getEntityContacts(
            [
                'withTotalCount' => true,
                'start'          => $start,
                'limit'          => $limit,
                'filter'         => ['string' => $search, 'force' => []],
                'orderBy'        => $orderBy,
                'orderByDir'     => $orderByDir,
                'route'          => $route,
            ],
            $spec['joinTable'],
            $entityId,
            $spec['contactFilter'],
            $spec['idColumn']
        );
        $count = (int) $contacts['count'];

        if ($count && $count < ($start + 1)) {
            // Fewer contacts than the page asked for: go to the last page, as core does.
            $lastPage = $pageHelper->countPage($count);
            $pageHelper->rememberPage($lastPage);

            return $this->postActionRedirect([
                'returnUrl'         => $this->generateUrl($route, ['objectId' => $entityId, 'page' => $lastPage]),
                'viewParameters'    => ['page' => $lastPage, 'objectId' => $entityId],
                'contentTemplate'   => $template,
                'forwardController' => false,
                'passthroughVars'   => ['mauticContent' => $sessionVar.'Contacts'],
            ]);
        }

        $pageHelper->rememberPage($page);

        $permissions = $this->security->isGranted(
            ['lead:leads:viewown', 'lead:leads:viewother', 'lead:leads:create', 'lead:leads:editown', 'lead:leads:editother', 'lead:leads:deleteown', 'lead:leads:deleteother'],
            'RETURN_ARRAY'
        );

        return $this->delegateView([
            'viewParameters' => array_merge($view, [
                'items'       => $contacts['results'],
                'totalItems'  => $count,
                'limit'       => $limit,
                'columns'     => $columns,
                'permissions' => $permissions,
                'security'    => $this->security,
            ]),
            'contentTemplate' => $template,
            'passthroughVars' => $pass,
        ]);
    }
}
