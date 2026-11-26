<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\SegmentRebuild;

use Doctrine\DBAL\Result;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\ContactSegmentService;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild\KeysetContactSegmentService;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild\KeysetRebuildSetting;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('area:segment')]
#[Group('risk:DADOS')]
#[Group('risk:INFRA')]
class KeysetContactSegmentServiceTest extends TestCase
{
    private const RUN = '2026-11-26 10:00:00';

    /** @var list<array{limiters: array, qb: MockObject}> */
    private array $built = [];

    /** @var list<\Throwable|list<array{id: string}>> rows (or an exception) of each query, in order */
    private array $queue = [];

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        if (!defined('MAUTIC_TABLE_PREFIX')) {
            define('MAUTIC_TABLE_PREFIX', '');
        }
        $this->built  = [];
        $this->queue  = [];
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function service(bool $enabled): KeysetContactSegmentService
    {
        $setting = $this->createMock(KeysetRebuildSetting::class);
        $setting->method('isEnabled')->willReturn($enabled);
        $test = $this;

        // the query builder of core needs the whole segment filter machinery: it is replaced by a recorder
        return new class($this->createMock(ContactSegmentFilterFactory::class), $this->createMock(ContactSegmentQueryBuilder::class), $this->logger, $setting, $test) extends KeysetContactSegmentService {
            public function __construct($f, $q, $l, $s, private KeysetContactSegmentServiceTest $test)
            {
                parent::__construct($f, $q, $l, $s);
            }

            public function getNewLeadListLeadsQueryBuilder(LeadList $segment, array $batchLimiters, bool $addNewContactsRestrictions = true): QueryBuilder
            {
                return $this->test->nextQuery($batchLimiters);
            }
        };
    }

    /** @internal used by the recorder subclass */
    public function nextQuery(array $limiters): QueryBuilder
    {
        $next = array_shift($this->queue);
        $qb   = $this->createMock(QueryBuilder::class);
        $qb->method('getTableAlias')->willReturn('l');
        $qb->method('getSQL')->willReturn('SELECT');
        $qb->method('getParameters')->willReturn([]);
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        if ($next instanceof \Throwable) {
            $qb->method('executeQuery')->willThrowException($next);
        } else {
            $result = $this->createMock(Result::class);
            $result->method('fetchAllAssociative')->willReturn($next ?? []);
            $qb->method('executeQuery')->willReturn($result);
        }
        $this->built[] = ['limiters' => $limiters, 'qb' => $qb];

        return $qb;
    }

    private function segment(int $id = 7): LeadList
    {
        $segment = $this->createMock(LeadList::class);
        $segment->method('getId')->willReturn($id);

        return $segment;
    }

    /** @return list<array{id: string}> */
    private function rows(int $from, int $to): array
    {
        return array_map(static fn (int $i) => ['id' => (string) $i], range($from, $to));
    }

    /** @return array<string, mixed> */
    private function limiters(array $extra = []): array
    {
        return ['dateTime' => self::RUN, 'maxId' => 1000] + $extra;
    }

    public function testItIsCoresSegmentServiceSoTheOverrideCanTakeItsId(): void
    {
        $this->assertTrue(is_subclass_of(KeysetContactSegmentService::class, ContactSegmentService::class));
        // the methods the keyset run relies on must still exist in the core class (a core upgrade that renames them breaks it)
        foreach (['getNewLeadListLeads', 'getNewLeadListLeadsCount', 'getNewLeadListLeadsQueryBuilder'] as $method) {
            $this->assertTrue(method_exists(ContactSegmentService::class, $method), $method);
        }
    }

    public function testSwitchOffRunsCoresQueryWithoutOrderByAndWithoutMinId(): void
    {
        $this->queue = [$this->rows(1, 3)];
        $service     = $this->service(false);

        $result = $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->assertSame([7 => $this->rows(1, 3)], $result);
        $this->assertCount(1, $this->built);
        $this->assertArrayNotHasKey('minId', $this->built[0]['limiters']);
    }

    public function testSwitchOnFirstBatchHasNoMinIdAndIsOrderedByIdWithTheLimit(): void
    {
        $this->queue = [$this->rows(1, 300)];
        $service     = $this->service(true);

        $result = $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->assertSame([7 => $this->rows(1, 300)], $result, 'same shape as core: [segmentId => rows]');
        $this->assertArrayNotHasKey('minId', $this->built[0]['limiters']);
        $this->assertSame(1000, $this->built[0]['limiters']['maxId']);
    }

    public function testOrderByIdAscAndLimitAreSetOnTheKeysetQuery(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getTableAlias')->willReturn('l');
        $qb->expects($this->once())->method('orderBy')->with('l.id', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('setMaxResults')->with(300)->willReturnSelf();
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($this->rows(1, 2));
        $qb->method('executeQuery')->willReturn($result);

        $svc = new class($this->createMock(ContactSegmentFilterFactory::class), $this->createMock(ContactSegmentQueryBuilder::class), $this->logger, $this->enabledSetting(), $qb) extends KeysetContactSegmentService {
            public function __construct($f, $q, $l, $s, private QueryBuilder $qb)
            {
                parent::__construct($f, $q, $l, $s);
            }

            public function getNewLeadListLeadsQueryBuilder(LeadList $segment, array $batchLimiters, bool $addNewContactsRestrictions = true): QueryBuilder
            {
                return $this->qb;
            }
        };

        $svc->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
    }

    private function enabledSetting(): KeysetRebuildSetting
    {
        $setting = $this->createMock(KeysetRebuildSetting::class);
        $setting->method('isEnabled')->willReturn(true);

        return $setting;
    }

    public function testSecondBatchContinuesAfterTheLastIdOfTheFirst(): void
    {
        $this->queue = [$this->rows(1, 300), $this->rows(301, 450)];
        $service     = $this->service(true);

        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->assertSame(301, $this->built[1]['limiters']['minId']);
        $this->assertSame(1000, $this->built[1]['limiters']['maxId'], 'maxId of the count stays');
    }

    public function testTheCursorUsesTheHighestIdEvenWhenRowsAreNotInOrder(): void
    {
        $this->queue = [[['id' => '5'], ['id' => '42'], ['id' => '9']], []];
        $service     = $this->service(true);

        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->assertSame(43, $this->built[1]['limiters']['minId']);
    }

    public function testAnEmptyBatchKeepsTheCursor(): void
    {
        $this->queue = [$this->rows(1, 10), [], []];
        $service     = $this->service(true);

        foreach ([1, 2, 3] as $_) {
            $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
        }

        $this->assertSame(11, $this->built[2]['limiters']['minId']);
    }

    public function testANewRunWithAnotherDateTimeStartsOver(): void
    {
        $this->queue = [$this->rows(1, 300), $this->rows(1, 5)];
        $service     = $this->service(true);

        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
        $service->getNewLeadListLeads($this->segment(), ['dateTime' => '2026-11-26 11:00:00'] + $this->limiters(), 300);

        $this->assertArrayNotHasKey('minId', $this->built[1]['limiters'], 'low ids that started matching are found by the next run (case E)');
    }

    public function testTheCountCallStartsTheRunOver(): void
    {
        $this->queue = [$this->rows(1, 300), $this->rows(1, 5)];
        $service     = $this->service(true);
        $segment     = $this->segment();

        $service->getNewLeadListLeads($segment, $this->limiters(), 300);
        try {
            // core's count needs the query builders, not available here: only the cursor reset before it matters
            $service->getNewLeadListLeadsCount($segment, $this->limiters());
        } catch (\Throwable) {
        }
        $service->getNewLeadListLeads($segment, $this->limiters(), 300);

        $this->assertArrayNotHasKey('minId', $this->built[1]['limiters']);
    }

    public function testCursorsOfDifferentSegmentsDoNotMix(): void
    {
        $this->queue = [$this->rows(1, 300), $this->rows(1, 3), $this->rows(301, 310)];
        $service     = $this->service(true);

        $service->getNewLeadListLeads($this->segment(7), $this->limiters(), 300);
        $service->getNewLeadListLeads($this->segment(8), $this->limiters(), 300);
        $service->getNewLeadListLeads($this->segment(7), $this->limiters(), 300);

        $this->assertArrayNotHasKey('minId', $this->built[1]['limiters']);
        $this->assertSame(301, $this->built[2]['limiters']['minId']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function callerLimitersProvider(): array
    {
        return [
            'lead_id from the caller' => [['lead_id' => 55]],
            'minId from the caller'   => [['minId' => 20]],
            'no dateTime'             => [['dateTime' => null]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('callerLimitersProvider')]
    public function testLimitersOfTheCallerMeanCoresBehaviour(array $override): void
    {
        $this->queue = [$this->rows(1, 3)];
        $service     = $this->service(true);
        $limiters    = array_merge($this->limiters(), $override);

        $service->getNewLeadListLeads($this->segment(), $limiters, 300);

        $this->assertSame($limiters, $this->built[0]['limiters'], 'passed through untouched');
    }

    public function testFirstBatchErrorFallsBackToCoreQueryForTheRestOfTheRun(): void
    {
        $this->queue = [new \RuntimeException('filesort died'), $this->rows(1, 300), $this->rows(301, 310)];
        $service     = $this->service(true);
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $first  = $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
        $second = $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->assertSame([7 => $this->rows(1, 300)], $first, 'the core query answered the first batch');
        $this->assertSame([7 => $this->rows(301, 310)], $second);
        $this->assertCount(3, $this->built);
        $this->assertArrayNotHasKey('minId', $this->built[2]['limiters'], 'fallback run stays on core: no minId');
    }

    public function testTheFallbackEndsWithTheRunAndTheCountCall(): void
    {
        $this->queue = [new \RuntimeException('boom'), $this->rows(1, 3), $this->rows(1, 300), $this->rows(301, 305)];
        $service     = $this->service(true);
        $segment     = $this->segment();

        $service->getNewLeadListLeads($segment, $this->limiters(), 300);   // fails, falls back
        try {
            $service->getNewLeadListLeadsCount($segment, $this->limiters());
        } catch (\Throwable) {
        }
        $service->getNewLeadListLeads($segment, $this->limiters(), 300);   // keyset again
        $service->getNewLeadListLeads($segment, $this->limiters(), 300);

        $this->assertSame(301, $this->built[3]['limiters']['minId'], 'the next run is keyset again, the flag was reset by the count call');
    }

    public function testAnErrorAfterRowsWereReturnedIsNotSwallowed(): void
    {
        $this->queue = [$this->rows(1, 300), new \RuntimeException('connection lost')];
        $service     = $this->service(true);

        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('connection lost');
        $service->getNewLeadListLeads($this->segment(), $this->limiters(), 300);
    }
}
