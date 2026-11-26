<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\ContactSegmentService;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Psr\Log\LoggerInterface;

/**
 * Segment rebuild with keyset pagination by contact id: each batch of ListModel::rebuildListLeads() starts after the last id of the
 * previous batch (core re-runs the whole filter query from the first contact on every batch). Switch off (KeysetRebuildSetting), a run that is not
 * ListModel's, or a failing first batch = core's behaviour.
 */
class KeysetContactSegmentService extends ContactSegmentService
{
    /**
     * Per segment id: run = the dateTime limiter of the run, lastId = last contact id returned, fallback = this run uses core's query.
     *
     * @var array<int, array{run: mixed, lastId: int, fallback: bool}>
     */
    private array $cursor = [];

    public function __construct(
        ContactSegmentFilterFactory $contactSegmentFilterFactory,
        ContactSegmentQueryBuilder $contactSegmentQueryBuilder,
        private LoggerInterface $keysetLogger,
        private KeysetRebuildSetting $setting
    ) {
        parent::__construct($contactSegmentFilterFactory, $contactSegmentQueryBuilder, $keysetLogger);
    }

    /**
     * The count is the first call of a run: the cursor of the segment starts over.
     */
    public function getNewLeadListLeadsCount(LeadList $segment, array $batchLimiters): array
    {
        unset($this->cursor[(int) $segment->getId()]);

        return parent::getNewLeadListLeadsCount($segment, $batchLimiters);
    }

    /**
     * @param mixed[] $batchLimiters
     * @param int     $limit
     *
     * @return array<int,mixed[]>
     */
    public function getNewLeadListLeads(LeadList $segment, array $batchLimiters, $limit = 1000): array
    {
        $segmentId = (int) $segment->getId();

        if (!$this->setting->isEnabled()
            || empty($batchLimiters['dateTime'])
            || isset($batchLimiters['lead_id'])
            || isset($batchLimiters['minId'])
            || ($this->cursor[$segmentId]['fallback'] ?? false)
        ) {
            return parent::getNewLeadListLeads($segment, $batchLimiters, $limit);
        }

        $cursor = $this->cursor[$segmentId] ?? null;
        if (null === $cursor || $cursor['run'] !== $batchLimiters['dateTime']) {
            $cursor = ['run' => $batchLimiters['dateTime'], 'lastId' => 0, 'fallback' => false];
        }

        $limiters = $batchLimiters;
        if ($cursor['lastId'] > 0) {
            $limiters['minId'] = $cursor['lastId'] + 1;
        }

        try {
            $queryBuilder = $this->getNewLeadListLeadsQueryBuilder($segment, $limiters);
            $queryBuilder->orderBy($queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads').'.id', 'ASC');
            $queryBuilder->setMaxResults($limit);

            $start = microtime(true);
            $rows  = $queryBuilder->executeQuery()->fetchAllAssociative();
            $this->keysetLogger->debug(
                sprintf('Segment QB (keyset): Query took: %.3fs. Result count: %d', microtime(true) - $start, count($rows)),
                ['segmentId' => $segmentId]
            );
        } catch (\Throwable $e) {
            if ($cursor['lastId'] > 0) {
                throw $e;
            }

            $this->keysetLogger->warning('Segment QB (keyset): first batch failed, this run uses the core query: '.$e->getMessage(), ['segmentId' => $segmentId]);
            $this->cursor[$segmentId] = ['run' => $cursor['run'], 'lastId' => 0, 'fallback' => true];

            return parent::getNewLeadListLeads($segment, $batchLimiters, $limit);
        }

        if ([] !== $rows) {
            $cursor['lastId'] = max($cursor['lastId'], (int) max(array_column($rows, 'id')));
        }
        $this->cursor[$segmentId] = $cursor;

        return [$segmentId => $rows];
    }
}
