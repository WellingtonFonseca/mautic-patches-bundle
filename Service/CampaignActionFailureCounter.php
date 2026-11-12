<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * How many logs are failed or being retried on each action step of a campaign: a failed log with the 'failed' flag that
 * PendingEvent::fail() sets, or still scheduled (the retry core made). Core also writes a failed log for a contact removed from
 * the campaign: that one has neither, so it is not counted (the same rule as CampaignActionContactSql::failed) (wiki/patches-campaign-actions-tab.md).
 */
class CampaignActionFailureCounter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<int> $eventIds
     *
     * @return array<int, int> event id => failed logs (a step with none is absent)
     */
    public function countByEvent(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        $sql = 'SELECT l.event_id, COUNT(*) AS failed FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log l'
            .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log f ON f.log_id = l.id'
            .' WHERE l.event_id IN (:ids) AND (l.is_scheduled = 1 OR l.metadata LIKE :flag) GROUP BY l.event_id';

        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $eventIds, 'flag' => CampaignActionContactSql::FAILED_FLAG],
            ['ids' => Connection::PARAM_INT_ARRAY]
        )->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['event_id']] = (int) $row['failed'];
        }

        return $counts;
    }
}
