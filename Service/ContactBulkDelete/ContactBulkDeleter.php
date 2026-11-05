<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\ContactBulkDelete;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;

/**
 * Finds the contacts of a contact-list search and deletes them in batches. The search is the one of the contact
 * list. LeadModel::getEntities() gives the repository its translator and current user; without them the search
 * commands (email:x) fail.
 */
class ContactBulkDeleter
{
    public const BATCH_SIZE = 200;

    public function __construct(private LeadModel $leadModel)
    {
    }

    public function count(string $search): int
    {
        return $this->find($search, 1)['count'];
    }

    /**
     * Deletes up to $limit contacts of the search. The deleted ones leave the result set, so the next call
     * finds the next ones.
     *
     * @return array{deleted: int, remaining: int}
     */
    public function deleteBatch(string $search, int $limit = self::BATCH_SIZE): array
    {
        $found = $this->find($search, $limit);
        $ids   = array_map('intval', array_keys($found['results']));

        if ([] === $ids) {
            return ['deleted' => 0, 'remaining' => 0];
        }

        $this->leadModel->deleteEntities($ids);

        return ['deleted' => count($ids), 'remaining' => max(0, $found['count'] - count($ids))];
    }

    /**
     * @return array{results: array<int, Lead>, count: int}
     */
    private function find(string $search, int $limit): array
    {
        $found = $this->leadModel->getEntities([
            'filter'         => ['string' => $search],
            'start'          => 0,
            'limit'          => $limit,
            'orderBy'        => 'l.id',
            'orderByDir'     => 'ASC',
            'withTotalCount' => true,
        ]);

        return ['results' => $found['results'], 'count' => (int) $found['count']];
    }
}
