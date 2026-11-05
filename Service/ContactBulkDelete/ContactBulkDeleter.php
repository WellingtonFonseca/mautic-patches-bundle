<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\ContactBulkDelete;

use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Counts the contacts of a contact-list search and deletes them in batches. The search is the one of the contact
 * list. LeadModel::getEntities() gives the repository its translator and current user; without them the search
 * commands (email:x) fail.
 */
class ContactBulkDeleter
{
    private const BATCH_SIZE = 200;

    public function __construct(private LeadModel $leadModel, private TranslatorInterface $translator)
    {
    }

    /**
     * What the contact list adds to a search: anonymous visitors are left out unless the search asks for them (core's LeadController).
     * One rule for the table, the count and the delete, so the three always agree on which contacts a search means.
     */
    public function force(string $search): string
    {
        $anonymous = $this->translator->trans('mautic.lead.lead.searchcommand.isanonymous');

        return str_contains($search, $anonymous) ? '' : ' !'.$anonymous;
    }

    public function count(string $search): int
    {
        $found = $this->leadModel->getEntities($this->query($search, 1) + ['withTotalCount' => true]);

        return (int) $found['count'];
    }

    /**
     * Deletes up to $limit contacts of the search. The deleted ones leave the result set, so the next call finds the next ones.
     * `done` is true when the batch was not full: nothing is left, so the caller does not need one more (empty) request, and the
     * batch does not pay for a count of the whole search each time.
     *
     * @return array{deleted: int, done: bool}
     */
    public function deleteBatch(string $search, int $limit = self::BATCH_SIZE): array
    {
        $ids = array_keys($this->leadModel->getEntities($this->query($search, $limit)));

        if ([] === $ids) {
            return ['deleted' => 0, 'done' => true];
        }

        $this->leadModel->deleteEntities($ids);

        return ['deleted' => count($ids), 'done' => count($ids) < $limit];
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $search, int $limit): array
    {
        return [
            'filter'     => ['string' => $search, 'force' => $this->force($search)],
            'start'      => 0,
            'limit'      => $limit,
            'orderBy'    => 'l.id',
            'orderByDir' => 'ASC',
        ];
    }
}
