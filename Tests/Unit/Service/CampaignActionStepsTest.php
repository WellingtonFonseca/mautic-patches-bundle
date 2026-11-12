<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\LeadEventLogRepository;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionSteps;
use PHPUnit\Framework\TestCase;

/**
 * Mautic 7: the steps and core's counts of the campaign page, as CampaignController::eventStatsAction computes them for its Ações tab.
 * The summary mode reads a final core repository (SummaryRepository): its call is not mocked, its conversion is (splitSummaryCounts).
 */
class CampaignActionStepsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $parameters = ['campaign_by_range' => 0, 'campaign_use_summary' => 0, 'campaign_event_cache_ttl' => 0];

    private function campaign(): Campaign
    {
        $campaign = $this->createStub(Campaign::class);
        $campaign->method('getId')->willReturn(9);

        return $campaign;
    }

    private function steps(?EventRepository $events = null, ?LeadEventLogRepository $logs = null): CampaignActionSteps
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Event::class, $events ?? $this->createStub(EventRepository::class)],
            [LeadEventLog::class, $logs ?? $this->createStub(LeadEventLogRepository::class)],
        ]);
        $parameters = $this->createStub(CoreParametersHelper::class);
        $parameters->method('get')->willReturnCallback(fn (string $name): mixed => $this->parameters[$name] ?? null);

        return new CampaignActionSteps($entityManager, $parameters);
    }

    public function testTheActionStepsOfThePageCampaignInCoresOrder(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->expects($this->once())->method('getCampaignEvents')->with(9, false)->willReturn([
            4 => ['id' => 4, 'eventType' => 'decision'],
            3 => ['id' => 3, 'eventType' => 'action'],
            7 => ['id' => 7, 'eventType' => 'condition'],
            5 => ['id' => 5, 'eventType' => 'action'],
        ]);

        $this->assertSame([['id' => 3, 'eventType' => 'action'], ['id' => 5, 'eventType' => 'action']], $this->steps($events)->actionSteps(['campaign' => $this->campaign()]));
    }

    public function testNoCampaignNoSteps(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->expects($this->never())->method('getCampaignEvents');

        $this->assertSame([], $this->steps($events)->actionSteps([]));
        $unsaved = $this->createStub(Campaign::class);
        $this->assertSame([], $this->steps($events)->actionSteps(['campaign' => $unsaved]), 'a campaign not saved yet has no steps');
    }

    public function testCountsAreCoresProcessedAndPendingOfEachStep(): void
    {
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->exactly(2))->method('getCampaignLogCounts')
            ->willReturnCallback(static fn (int $id, bool $excludeScheduled, bool $excludeNegative, bool $all, $from, $to): array => 9 === $id && !$excludeNegative && !$all && null === $from && null === $to
                ? ($excludeScheduled ? [3 => [0 => 1, 1 => 4]] : [3 => [0 => 2, 1 => 6], 5 => [0 => 0, 1 => 1]])
                : []);

        $steps = $this->steps(null, $logs)->withLogCounts([['id' => 3], ['id' => 5], ['id' => 6]], ['campaign' => $this->campaign()]);

        $this->assertSame([
            ['id' => 3, 'logCountProcessed' => 5, 'logCountForPending' => 3],
            ['id' => 5, 'logCountProcessed' => 0, 'logCountForPending' => 1],
            ['id' => 6, 'logCountProcessed' => 0, 'logCountForPending' => 0],
        ], $steps);
    }

    public function testTheDateRangeOfThePageCountsOnlyWhenCampaignByRangeIsOnWithTheLastDayIncluded(): void
    {
        $this->parameters['campaign_by_range'] = 1;
        $seen = [];
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->exactly(2))->method('getCampaignLogCounts')
            ->willReturnCallback(static function (int $id, bool $s, bool $n, bool $a, $from, $to) use (&$seen): array {
                $seen[] = [$from?->format('Y-m-d H:i'), $to?->format('Y-m-d H:i')];

                return [];
            });

        $this->steps(null, $logs)->withLogCounts([['id' => 3]], ['campaign' => $this->campaign(), 'dateFrom' => 'Oct 1, 2026', 'dateTo' => 'Oct 31, 2026']);

        $this->assertSame([['2026-10-01 00:00', '2026-11-01 00:00'], ['2026-10-01 00:00', '2026-11-01 00:00']], $seen);
    }

    public function testADateObjectIsReadTooWithItsTimeDropped(): void
    {
        $this->parameters['campaign_by_range'] = 1;
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->exactly(2))->method('getCampaignLogCounts')
            ->with(9, $this->anything(), false, false, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-11-01'), null, 0)->willReturn([]);

        $this->steps(null, $logs)->withLogCounts([['id' => 3]], ['campaign' => $this->campaign(), 'dateFrom' => new \DateTime('2026-10-01 15:00'), 'dateTo' => new \DateTime('2026-10-31 15:00')]);
    }

    public function testARangeWithOnlyOneEndIsNoRange(): void
    {
        $this->parameters['campaign_by_range'] = 1;
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->exactly(2))->method('getCampaignLogCounts')
            ->with(9, $this->anything(), false, false, null, null, null, 0)->willReturn([]);

        $this->steps(null, $logs)->withLogCounts([['id' => 3]], ['campaign' => $this->campaign(), 'dateFrom' => 'Oct 1, 2026', 'dateTo' => '']);
    }

    public function testWithoutCampaignByRangeThePageDatesAreIgnored(): void
    {
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->exactly(2))->method('getCampaignLogCounts')
            ->with(9, $this->anything(), false, false, null, null, null, 0)->willReturn([]);

        $this->steps(null, $logs)->withLogCounts([['id' => 3]], ['campaign' => $this->campaign(), 'dateFrom' => new \DateTime('2026-10-01'), 'dateTo' => new \DateTime('2026-10-31')]);
    }

    public function testNoStepsOrNoCampaignReadsNoCount(): void
    {
        $logs = $this->createMock(LeadEventLogRepository::class);
        $logs->expects($this->never())->method('getCampaignLogCounts');

        $this->assertSame([], $this->steps(null, $logs)->withLogCounts([], ['campaign' => $this->campaign()]));
        $this->assertSame([['id' => 3], ['id' => 5]], $this->steps(null, $logs)->withLogCounts([['id' => 3], ['id' => 5]], []), 'every step back, untouched');
    }

    public function testTheSummaryRowsThirdValueIsTheProcessedCount(): void
    {
        $this->assertSame(
            [[3 => [0 => 2, 1 => 6], 5 => [0 => 1, 1 => 0]], [3 => [5], 5 => [0]]],
            CampaignActionSteps::splitSummaryCounts([3 => [0 => 2, 1 => 6, 2 => 5], 5 => [0 => 1, 1 => 0]])
        );
    }
}
