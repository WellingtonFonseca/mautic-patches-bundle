<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

/**
 * Adds the DISTINCT predicate to the query builder core makes for a segment (new members, their count, and the
 * "matches the filters" query the orphans are found with: LIST_FILTERS_QUERYBUILDER_GENERATED).
 *
 * The pool is "the contacts that match the segment filters, with every chosen field filled, not removed by hand from
 * this segment". The winner of a group (same value in all chosen fields) is its lowest id. A contact is kept when it is
 * its group's winner. A contact with a chosen field empty (NULL or ''): leaves the segment (remove empty ON) or stays
 * and skips the DISTINCT (OFF). Values are compared with the column collation (case and trailing spaces ignored).
 *
 * It is all SQL, so the batches of the rebuild (no offset, "not yet in the segment") still converge.
 */
class SegmentDistinctQuery
{
    public function __construct(
        private SegmentDistinctStore $store,
        private IndexedLeadFields $indexedFields,
        private ContactSegmentQueryBuilder $segmentQueryBuilder,
        private ContactSegmentFilterFactory $filterFactory,
        private LoggerInterface $logger
    ) {
    }

    public function apply(LeadList $segment, QueryBuilder $queryBuilder): void
    {
        $segmentId = $segment->getId();
        $config    = $this->store->get(null === $segmentId ? null : (int) $segmentId);
        if (null === $segmentId || null === $config || !$config->enabled || [] === $config->fields) {
            return;
        }

        $fields = [];
        foreach ($config->fields as $field) {
            if (1 === preg_match('/^[a-z0-9_]+$/i', $field) && $this->indexedFields->columnExists($field)) {
                $fields[$field] = $this->indexedFields->isTextColumn($field);
            } else {
                $this->logger->warning('Segment DISTINCT: field "'.$field.'" is not a column of the contacts table any more, ignored (segment '.$segmentId.').');
            }
        }
        if ([] === $fields) {
            return;
        }

        $pool      = $this->segmentQueryBuilder->assembleContactsSegmentQueryBuilder(
            $segmentId,
            $this->filterFactory->getSegmentFilters($segment),
            true
        );
        $poolAlias = $pool->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');
        foreach ($fields as $field => $isText) {
            $pool->addSelect($poolAlias.'.'.$field);
            $pool->andWhere($poolAlias.'.'.$field.' IS NOT NULL'.($isText ? ' AND '.$poolAlias.'.'.$field." <> ''" : ''));
        }
        $pool->andWhere(sprintf(
            'NOT EXISTS (SELECT 1 FROM %s mr WHERE mr.lead_id = %s.id AND mr.leadlist_id = %d AND mr.manually_removed = 1)',
            MAUTIC_TABLE_PREFIX.'lead_lists_leads',
            $poolAlias,
            (int) $segmentId
        ));

        $alias   = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');
        $groupBy = implode(', ', array_map(static fn (string $field): string => 'dp.'.$field, array_keys($fields)));
        $winners = $alias.'.id IN (SELECT MIN(dp.id) FROM ('.$pool->getSQL().') dp GROUP BY '.$groupBy.')';

        $filledParts = $emptyParts = [];
        foreach ($fields as $field => $isText) {
            $filledParts[] = $alias.'.'.$field.' IS NOT NULL'.($isText ? " AND {$alias}.{$field} <> ''" : '');
            $emptyParts[]  = $alias.'.'.$field.' IS NULL'.($isText ? " OR {$alias}.{$field} = ''" : '');
        }
        $filled = implode(' AND ', $filledParts);
        $empty  = implode(' OR ', $emptyParts);

        $queryBuilder->andWhere($config->removeEmpty ? $filled.' AND '.$winners : '('.$empty.' OR '.$winners.')');

        $queryBuilder->setParameters(
            array_merge($queryBuilder->getParameters(), $pool->getParameters()),
            array_merge($queryBuilder->getParameterTypes(), $pool->getParameterTypes())
        );
    }
}
