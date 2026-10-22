<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RelativeWait;

use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitRecalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Recalculating the contacts that already wait at a Wait node: each scheduled log is given the date the CURRENT rule of the node
 * gives its contact (the same resolver, selector and "past or missing is now" rule the scheduler uses). A plan only reads;
 * applying it is what writes the dates.
 */
class RelativeWaitRecalculatorTest extends TestCase
{
    private \DateTimeImmutable $now;

    /** @var Connection&MockObject */
    private MockObject $connection;

    /** @var RelativeWaitDateResolver&MockObject */
    private MockObject $resolver;

    /** @var RelativeWaitItemSelector&MockObject */
    private MockObject $selector;

    /** @var array<int, array{string, array<string, mixed>}> */
    private array $writes = [];

    protected function setUp(): void
    {
        $this->now        = new \DateTimeImmutable('2026-03-10 12:00:00', new \DateTimeZone('UTC'));
        $this->connection = $this->createMock(Connection::class);
        $this->resolver   = $this->createMock(RelativeWaitDateResolver::class);
        $this->selector   = $this->createMock(RelativeWaitItemSelector::class);
        $this->writes     = [];
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params = [], array $types = []): int {
            $this->writes[] = [$sql, $params];

            return count($params['ids'] ?? []);
        });
    }

    private function recalculator(): RelativeWaitRecalculator
    {
        return new RelativeWaitRecalculator($this->connection, $this->resolver, $this->selector);
    }

    private function waitEvent(string $type = RelativeWaitSubscriber::TYPE): Event
    {
        $campaign = $this->createMock(Campaign::class);
        $campaign->method('getId')->willReturn(3);
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(42);
        $event->method('getType')->willReturn($type);
        $event->method('getCampaign')->willReturn($campaign);
        $event->method('getProperties')->willReturn(['dateField' => 'contact:attribution_date', 'amount' => 12, 'unit' => 'd', 'direction' => 'after']);

        return $event;
    }

    /**
     * @param array<int, string> $logs contact id => the date the log is scheduled for now
     */
    private function scheduledLogs(array $logs): void
    {
        $rows = [];
        foreach ($logs as $contactId => $date) {
            $rows[] = ['id' => (string) (100 + $contactId), 'lead_id' => (string) $contactId, 'trigger_date' => $date];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);
    }

    /**
     * @param array<int, ?string> $dates contact id => the date the current rule gives (null: the contact has none)
     */
    private function ruleGives(array $dates): void
    {
        $this->resolver->method('resolve')->willReturnCallback(
            fn (int $contactId): ?\DateTimeImmutable => isset($dates[$contactId]) ? new \DateTimeImmutable($dates[$contactId], new \DateTimeZone('UTC')) : null
        );
    }

    private function statusOf(array $plan, int $contactId): string
    {
        foreach ($plan['rows'] as $row) {
            if ($row['contact'] === $contactId) {
                return $row['status'];
            }
        }

        return 'no row';
    }

    public function testEachScheduledContactIsClassified(): void
    {
        $this->scheduledLogs([
            1 => '2026-03-20 03:00:00', // the rule gives another day: changes
            2 => '2026-03-20 03:00:00', // the rule gives a day already past: leaves now
            3 => '2026-03-20 03:00:00', // the rule gives the same date: unchanged
            4 => '2026-03-20 03:00:00', // no date: leaves now
            5 => '2026-03-09 03:00:00', // already due, the rule still gives a past date: unchanged
        ]);
        $this->ruleGives([1 => '2026-03-25 03:00:00', 2 => '2026-03-01 03:00:00', 3 => '2026-03-20 03:00:00', 5 => '2026-03-02 03:00:00']);

        $plan = $this->recalculator()->plan($this->waitEvent(), $this->now);

        $this->assertSame('changed', $this->statusOf($plan, 1));
        $this->assertSame('now', $this->statusOf($plan, 2));
        $this->assertSame('unchanged', $this->statusOf($plan, 3));
        $this->assertSame('now', $this->statusOf($plan, 4));
        $this->assertSame('unchanged', $this->statusOf($plan, 5));
        $this->assertSame(['total' => 5, 'changed' => 1, 'leaving_now' => 2, 'unchanged' => 2, 'without_date' => 1], $plan['summary']);
    }

    public function testAPlanOnlyReadsAndNeverWrites(): void
    {
        $this->scheduledLogs([1 => '2026-03-20 03:00:00']);
        $this->ruleGives([1 => '2026-03-25 03:00:00']);
        $this->connection->expects($this->never())->method('executeStatement');

        $this->recalculator()->plan($this->waitEvent(), $this->now);
    }

    public function testTheRowsCarryTheOldAndTheNewDate(): void
    {
        $this->scheduledLogs([1 => '2026-03-20 03:00:00', 2 => '2026-03-20 03:00:00']);
        $this->ruleGives([1 => '2026-03-25 03:00:00', 2 => '2026-03-01 03:00:00']);

        $plan = $this->recalculator()->plan($this->waitEvent(), $this->now);

        $this->assertSame(101, $plan['rows'][0]['log']);
        $this->assertSame('2026-03-20 03:00:00', $plan['rows'][0]['old']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-25 03:00:00', $plan['rows'][0]['new']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 12:00:00', $plan['rows'][1]['new']->format('Y-m-d H:i:s'), 'a past date is now');
    }

    public function testTheSegmentSelectedItemsAreAskedForEachContactOfTheCampaign(): void
    {
        $this->scheduledLogs([5 => '2026-03-20 03:00:00']);
        $event = $this->waitEvent();
        $this->selector->expects($this->once())->method('itemIds')
            ->with($this->callback(static fn (Lead $contact): bool => 5 === $contact->getId()), $event->getCampaign(), $event->getProperties())
            ->willReturn([4, 9]);
        $this->resolver->expects($this->once())->method('resolve')->with(5, $event->getProperties(), [4, 9])->willReturn(null);

        $this->recalculator()->plan($event, $this->now);
    }

    public function testApplyingWritesOnlyTheLogsThatChangeGroupedByDate(): void
    {
        $this->scheduledLogs([1 => '2026-03-20 03:00:00', 2 => '2026-03-20 03:00:00', 3 => '2026-03-20 03:00:00', 4 => '2026-03-20 03:00:00']);
        $this->ruleGives([1 => '2026-03-25 03:00:00', 2 => '2026-03-25 03:00:00', 3 => '2026-03-20 03:00:00', 4 => '2026-03-01 03:00:00']);
        $recalculator = $this->recalculator();

        $updated = $recalculator->apply($recalculator->plan($this->waitEvent(), $this->now));

        $this->assertSame(3, $updated, 'two change date, one leaves now; the unchanged one is not touched');
        $this->assertCount(2, $this->writes, 'one UPDATE per distinct new date');
        $byDate = [];
        foreach ($this->writes as [$sql, $params]) {
            $this->assertStringContainsString('UPDATE campaign_lead_event_log SET trigger_date', $sql);
            $this->assertStringContainsString('is_scheduled = 1', $sql, 'a log that already ran is never moved');
            $byDate[$params['date']] = $params['ids'];
        }
        $this->assertSame([101, 102], $byDate['2026-03-25 03:00:00']);
        $this->assertSame([104], $byDate['2026-03-10 12:00:00']);
    }

    public function testApplyingAPlanWithNothingToChangeWritesNothing(): void
    {
        $this->scheduledLogs([1 => '2026-03-20 03:00:00']);
        $this->ruleGives([1 => '2026-03-20 03:00:00']);
        $this->connection->expects($this->never())->method('executeStatement');
        $recalculator = $this->recalculator();

        $this->assertSame(0, $recalculator->apply($recalculator->plan($this->waitEvent(), $this->now)));
    }

    public function testAnotherEventIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->recalculator()->plan($this->waitEvent('email.send'), $this->now);
    }

    public function testNoContactWaitingIsAnEmptyPlan(): void
    {
        $this->scheduledLogs([]);

        $plan = $this->recalculator()->plan($this->waitEvent(), $this->now);

        $this->assertSame(['total' => 0, 'changed' => 0, 'leaving_now' => 0, 'unchanged' => 0, 'without_date' => 0], $plan['summary']);
    }
}
