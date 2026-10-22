<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Services\ContactColumnsDictionary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The contacts tab of a segment / campaign page as the contact list TABLE (same columns, in the order set in
 * Configuration > Contact Settings) instead of core's cards, and the contacts list of the campaign node modals. Used by
 * SegmentContactsController, CampaignContactsController and CampaignPendingContactsController: the one place of this table.
 *
 * The first load of the tab (a GET without `tmpl`) comes with the search bar of the contact list over the table
 * (contacts_table.html.twig), the table in an element of its own; every later request (the live search: a GET with `tmpl`;
 * sorting, paging, page size: POSTs with `tmpl`; the redirect to the last page: `body`) only brings the table, into that
 * element, so the bar is not replaced while it is being typed in. The first load starts
 * with an empty search (the session's search of the last visit is dropped).
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
     *     permission: string|string[], joinTable: string, idColumn: string, contactFilter: array<int|string, int|array<string, bool|string>>,
     *     orderBy: string, orderByDir?: string, additionalJoins?: array<int, array<string, string>>, routeId?: int
     * } $spec container = the selector of the tab pane the table is loaded into (the target of sorting/paging);
     *         orderByDir = the direction of the default order (DESC); additionalJoins = extra joins of the query (core's format);
     *         routeId = the `objectId` of the route, when it is not the id the join table is filtered by (entityId)
     */
    private function contactsTable(Request $request, PageHelperFactoryInterface $pageHelperFactory, array $spec): Response
    {
        $sessionVar = $spec['sessionVar'];
        $route      = $spec['route'];
        $entityId   = $spec['entityId'];
        $routeId    = $spec['routeId'] ?? $entityId;
        $page       = $spec['page'];
        $template   = '@MauticPatches/Contact/contacts_table.html.twig';
        $outer      = $spec['container'];
        $bodyId     = ltrim($outer, '#').'-table';
        $baseUrl    = $this->generateUrl($route, ['objectId' => $routeId]);
        // core's own requests of a list (search, sorting, paging, the page size) all carry `tmpl`; the first load of the tab does not
        $withBar    = 'GET' === $request->getMethod() && !$request->query->has('tmpl') && !$request->query->has('body');
        $view       = [
            'page'          => $page,
            'tmpl'          => $sessionVar.'Contacts',
            'baseUrl'       => $baseUrl,
            'sessionVar'    => $sessionVar.'.contact',
            // the target of sorting, paging and the search: the table's own element, so the search bar stays
            'container'     => '#'.$bodyId,
            'outer'         => $outer,
            'toolbar'       => $withBar,
            'searchId'      => 'search-'.ltrim($outer, '#'),
            'searchValue'   => '',
            'searchAction'  => $baseUrl,
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
        $search  = $withBar
            ? (string) $request->query->get('search', '')
            : $request->get('search', $session->get('mautic.'.$sessionVar.'.contact.filter', ''));
        $session->set('mautic.'.$sessionVar.'.contact.filter', $search);
        $view['searchValue'] = $search;

        $pageHelper = $pageHelperFactory->make("mautic.{$sessionVar}", $page);
        $orderBy    = $session->get('mautic.'.$sessionVar.'.contact.orderby', $spec['orderBy']);
        $orderByDir = $session->get('mautic.'.$sessionVar.'.contact.orderbydir', $spec['orderByDir'] ?? 'DESC');
        $limit      = $session->get('mautic.'.$sessionVar.'.contact.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start      = max(0, (1 === $page) ? 0 : (($page - 1) * $limit));
        $columns    = $this->columnsDictionary->getColumns();

        /** @var LeadRepository $repo */
        $repo = $this->getModel('lead')->getRepository();
        // What the model's getEntities() gives its repository before it searches: the current user (`is:mine` and the owner
        // commands need it). The translator the search commands need is autowired into the repository on 7 (CommonRepository's
        // #[Required] method; there is no setTranslator() as on 5).
        $repo->setCurrentUser($this->user);
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
            $spec['idColumn'],
            $spec['additionalJoins'] ?? null
        );
        $count = (int) $contacts['count'];

        if ($count && $count < ($start + 1)) {
            // Fewer contacts than the page asked for: go to the last page, as core does.
            $lastPage = $pageHelper->countPage($count);
            $pageHelper->rememberPage($lastPage);

            return $this->postActionRedirect([
                'returnUrl'         => $this->generateUrl($route, ['objectId' => $routeId, 'page' => $lastPage, 'body' => 1]),
                'viewParameters'    => ['page' => $lastPage, 'objectId' => $routeId],
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
