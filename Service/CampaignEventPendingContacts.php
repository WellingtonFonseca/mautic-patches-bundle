<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * The contacts waiting at one event of a campaign: the ones the hourglass of the node counts. The conditions are the ones of
 * core's count (LeadEventLogRepository::getCampaignLogCounts, all logs minus the done ones): a log of the event that is
 * still scheduled, of a contact that is a member of the campaign, on the positive path, not failed.
 */
class CampaignEventPendingContacts
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{total: int, rows: list<array{id: int, name: string, email: string, scheduled: ?string}>} `scheduled` is the
     *                                                                                                      trigger date as stored (UTC), the earliest first
     */
    public function find(int $eventId, int $campaignId, int $limit): array
    {
        $from = ' FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log o'
            .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaign_leads m ON m.campaign_id = o.campaign_id AND m.lead_id = o.lead_id'
            .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'leads c ON c.id = o.lead_id'
            .' WHERE o.event_id = :event AND o.campaign_id = :campaign AND o.is_scheduled = 1'
            .' AND (o.non_action_path_taken IS NULL OR o.non_action_path_taken = 0)'
            .' AND NOT EXISTS (SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log f WHERE f.log_id = o.id)';
        $params = ['event' => $eventId, 'campaign' => $campaignId];

        $total = (int) $this->connection->fetchOne('SELECT COUNT(o.id)'.$from, $params);
        $rows  = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.firstname, c.lastname, c.email, o.trigger_date'.$from.' ORDER BY o.trigger_date ASC, o.id ASC LIMIT '.max(1, $limit),
            $params
        );

        return [
            'total' => $total,
            'rows'  => array_map(static fn (array $row): array => [
                'id'        => (int) $row['id'],
                'name'      => self::name($row),
                'email'     => (string) $row['email'],
                'scheduled' => $row['trigger_date'] ?: null,
            ], $rows),
        ];
    }

    /**
     * First and last name, else the email, else the id: a contact always has something to read.
     *
     * @param array<string, mixed> $row
     */
    private static function name(array $row): string
    {
        $name = trim(($row['firstname'] ?? '').' '.($row['lastname'] ?? ''));

        return '' !== $name ? $name : ((string) ($row['email'] ?? '') ?: '#'.$row['id']);
    }
}
