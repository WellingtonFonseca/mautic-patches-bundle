<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;

/**
 * Gives the contacts that already wait at a Wait node (patches.wait_relative) the date the CURRENT rule of the node gives them.
 * A contact keeps the date calculated when it reached the node, so a changed rule (or an edited start date) only reaches the ones
 * arriving afterwards; this is how it reaches the ones already waiting.
 *
 * plan() only reads: for each scheduled log, the date now (the scheduler's rule: the resolver's date, "now" when it is past or the
 * contact has none) next to the one stored, and a status:
 *   - changed:   another date in the future;
 *   - now:       the new date has passed (or there is none), the old one had not: it leaves on the next run of the campaign;
 *   - unchanged: the same date, or already due and still due.
 * apply() writes the dates of the changed and the now rows (one UPDATE per distinct date), only on logs that are still scheduled.
 */
class RelativeWaitRecalculator
{
    public const CHANGED   = 'changed';
    public const NOW       = 'now';
    public const UNCHANGED = 'unchanged';

    public const FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Connection $connection,
        private RelativeWaitDateResolver $dateResolver,
        private RelativeWaitItemSelector $itemSelector,
    ) {
    }

    /**
     * @return array{
     *     rows: list<array{log: int, contact: int, old: \DateTimeImmutable, new: \DateTimeImmutable, status: string, without_date: bool}>,
     *     summary: array{total: int, changed: int, leaving_now: int, unchanged: int, without_date: int}
     * }
     */
    public function plan(Event $event, ?\DateTimeImmutable $now = null): array
    {
        if (!RelativeWaitSubscriber::isWait($event)) {
            throw new \InvalidArgumentException('Only a Wait node can be recalculated.');
        }

        $now     = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $config  = (array) $event->getProperties();
        $prefix  = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        $rows    = [];
        $summary = ['total' => 0, 'changed' => 0, 'leaving_now' => 0, 'unchanged' => 0, 'without_date' => 0];

        $logs = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.lead_id, l.trigger_date FROM '.$prefix.'campaign_lead_event_log l'
            .' WHERE l.event_id = :event AND l.is_scheduled = 1'
            .' AND NOT EXISTS (SELECT 1 FROM '.$prefix.'campaign_lead_event_failed_log f WHERE f.log_id = l.id)'
            .' ORDER BY l.id',
            ['event' => $event->getId()]
        );

        foreach ($logs as $log) {
            $contact = new Lead();
            $contact->setId((int) $log['lead_id']);

            $old     = new \DateTimeImmutable((string) $log['trigger_date'], new \DateTimeZone('UTC'));
            $date    = $this->dateResolver->resolve((int) $log['lead_id'], $config, $this->itemSelector->itemIds($contact, $event->getCampaign(), $config));
            $new     = null !== $date && $date > $now ? $date : $now;
            $status  = $this->status($old, $new, $now);

            $rows[] = ['log' => (int) $log['id'], 'contact' => (int) $log['lead_id'], 'old' => $old, 'new' => $new, 'status' => $status, 'without_date' => null === $date];

            ++$summary['total'];
            ++$summary[self::NOW === $status ? 'leaving_now' : $status];
            $summary['without_date'] += null === $date ? 1 : 0;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param array{rows: list<array{log: int, contact: int, old: \DateTimeImmutable, new: \DateTimeImmutable, status: string, without_date: bool}>, summary: array<string, int>} $plan
     *
     * @return int the logs updated
     */
    public function apply(array $plan): int
    {
        $byDate = [];
        foreach ($plan['rows'] as $row) {
            if (self::UNCHANGED !== $row['status']) {
                $byDate[$row['new']->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT)][] = $row['log'];
            }
        }

        $prefix  = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        $updated = 0;
        foreach ($byDate as $date => $ids) {
            $updated += (int) $this->connection->executeStatement(
                'UPDATE '.$prefix.'campaign_lead_event_log SET trigger_date = :date WHERE id IN (:ids) AND is_scheduled = 1',
                ['date' => $date, 'ids' => $ids],
                ['ids' => ArrayParameterType::INTEGER]
            );
        }

        return $updated;
    }

    private function status(\DateTimeImmutable $old, \DateTimeImmutable $new, \DateTimeImmutable $now): string
    {
        if ($old == $new || ($old <= $now && $new <= $now)) {
            return self::UNCHANGED;
        }

        return $new <= $now ? self::NOW : self::CHANGED;
    }
}
