<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use MauticPlugin\MauticPatchesBundle\Service\ContactBulkDelete\ContactBulkDeleter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delete many contacts at once: the page, the count of a search, its table and one batch per POST. The page script
 * repeats the POST until nothing is left. Only for who may delete other users' contacts, and the search is never
 * empty (an empty search would match every contact).
 */
class ContactBulkDeleteController extends CommonController
{
    use ContactsTableTrait;

    /** One rule for the pages and for the menu entry. */
    public const PERMISSION = 'lead:leads:deleteother';

    public function indexAction(): Response
    {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'contentTemplate' => '@MauticPatches/Contact/bulk_delete.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_contact_index',
                'mauticContent' => 'patchesContactBulkDelete',
                'route'         => $this->generateUrl('mautic_patches_contact_bulk_delete'),
            ],
        ]);
    }

    public function countAction(Request $request, ContactBulkDeleter $deleter): Response
    {
        return $this->withSearch($request, static fn (string $search): array => ['total' => $deleter->count($search)]);
    }

    /**
     * The contacts of the search as the contact list table (see ContactsTableTrait), what "Delete all" will delete. The first load
     * (a GET with ?search=) keeps the search in the session, the sorting and paging that follow read it from there.
     *
     * The route has an `objectId` (always 0) because the table's pagination builds its links from it; this action does not need it.
     *
     * @param int|string $page
     */
    public function contactsAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, ContactBulkDeleter $deleter, $page = 1): Response
    {
        return $this->contactsTable($request, $pageHelperFactory, [
            'entityId'       => 0,
            'page'           => (int) $page,
            'sessionVar'     => 'contactBulkDelete',
            'route'          => 'mautic_patches_contact_bulk_delete_contacts',
            'container'      => '#contact-bulk-delete-result',
            'permission'     => self::PERMISSION,
            'orderBy'        => 'l.id',
            'orderByDir'     => 'ASC',
            'forceFilter'    => $deleter->force(...),
            'toolbarActions' => $this->renderView('@MauticPatches/Contact/bulk_delete_button.html.twig'),
        ]);
    }

    public function deleteAction(Request $request, ContactBulkDeleter $deleter): Response
    {
        return $this->withSearch($request, static fn (string $search): array => $deleter->deleteBatch($search));
    }

    /**
     * @param callable(string): array<mixed> $action
     */
    private function withSearch(Request $request, callable $action): Response
    {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return new JsonResponse(['error' => 'access_denied'], Response::HTTP_FORBIDDEN);
        }

        $search = trim((string) ($request->isMethod('POST') ? $request->request : $request->query)->get('search', ''));

        if ('' === $search) {
            return new JsonResponse(['error' => 'empty_search'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($action($search));
    }
}
