<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * How many contacts have a failed dispatch on each action step of a campaign (the failures of the step, retries included).
 * Core also writes a failed log for a contact removed from the campaign: that one has no 'failed' flag in the metadata that
 * PendingEvent::fail() sets, so it is not counted (wiki/patches-campaign-actions-tab.md).
 */
class CampaignActionFailureCounter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<int> $eventIds
     *
     * @return array<int, int> event id => failed contacts (a step with none is absent)
     */
    public function countByEvent(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        $sql = 'SELECT l.event_id, COUNT(*) AS failed FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log l'
            .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log f ON f.log_id = l.id'
            .' WHERE l.event_id IN (:ids) AND l.metadata LIKE :flag GROUP BY l.event_id';

        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $eventIds, 'flag' => '%s:6:"failed";i:1;%'],
            ['ids' => Connection::PARAM_INT_ARRAY]
        )->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['event_id']] = (int) $row['failed'];
        }

        return $counts;
    }
}
