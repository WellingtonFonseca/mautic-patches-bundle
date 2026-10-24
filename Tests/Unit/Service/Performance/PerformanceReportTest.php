<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\Performance;

use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceReport;
use PHPUnit\Framework\TestCase;

class PerformanceReportTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function entries(int $n, float $ms, float $db, int $q, string $path = '/api/x'): array
    {
        return array_fill(0, $n, ['t' => time(), 'm' => 'GET', 'p' => $path, 's' => 200, 'ms' => $ms, 'db' => $db, 'q' => $q]);
    }

    public function testNoDataWhenThereAreNoEntries(): void
    {
        $this->assertSame(PerformanceReport::NO_DATA, (new PerformanceReport())->build([])['verdict']);
    }

    public function testFastRequestsAreHealthy(): void
    {
        $this->assertSame(PerformanceReport::HEALTHY, (new PerformanceReport())->build($this->entries(10, 100, 50, 5))['verdict']);
    }

    public function testSlowWithMostTimeInSqlAndFewQueriesIsSlowQueries(): void
    {
        $this->assertSame(PerformanceReport::DB_SLOW_QUERIES, (new PerformanceReport())->build($this->entries(10, 2000, 1700, 6))['verdict']);
    }

    public function testSlowWithMostTimeInSqlAndManyQueriesIsManyQueries(): void
    {
        $this->assertSame(PerformanceReport::DB_MANY_QUERIES, (new PerformanceReport())->build($this->entries(10, 2000, 1700, 120))['verdict']);
    }

    public function testSlowWithLittleSqlIsTheApplication(): void
    {
        $this->assertSame(PerformanceReport::APP, (new PerformanceReport())->build($this->entries(10, 2000, 200, 6))['verdict']);
    }

    public function testSlowWithHalfSqlIsMixed(): void
    {
        $this->assertSame(PerformanceReport::MIXED, (new PerformanceReport())->build($this->entries(10, 2000, 1000, 6))['verdict']);
    }

    public function testStatsAndGroupingPerRouteWithP95(): void
    {
        $entries = array_merge($this->entries(19, 100, 10, 2, '/api/a'), $this->entries(1, 900, 800, 40, '/api/b'));
        $report  = (new PerformanceReport())->build($entries);

        $this->assertSame(20, $report['total']['count']);
        $this->assertSame(900.0, $report['total']['max']);
        $this->assertSame(1, $report['total']['slow']);
        $this->assertSame('GET /api/b', $report['routes'][0]['name']);
        $this->assertSame('GET /api/b', $report['slowest'][0]['m'].' '.$report['slowest'][0]['p']);
    }

    public function testErrorsCountOnlyServerErrors(): void
    {
        $entries   = $this->entries(3, 100, 10, 2);
        $entries[0]['s'] = 500;
        $entries[1]['s'] = 404;

        $this->assertSame(1, (new PerformanceReport())->stats($entries)['errors']);
    }

    public function testNoEntriesGiveZeroStats(): void
    {
        $this->assertSame(
            ['count' => 0, 'avg' => 0.0, 'p95' => 0.0, 'max' => 0.0, 'avgDb' => 0.0, 'avgQueries' => 0.0, 'dbShare' => 0.0, 'slow' => 0, 'errors' => 0],
            (new PerformanceReport())->stats([])
        );
    }

    public function testTheSlowLimitComesFromTheEnvironmentWhenItIsAPositiveNumber(): void
    {
        try {
            putenv('MAUTIC_PERF_SLOW_MS=1500');
            $this->assertSame(1500, PerformanceReport::slowLimit());
            foreach (['0', '-5', 'abc', ''] as $bad) {
                putenv('MAUTIC_PERF_SLOW_MS='.$bad);
                $this->assertSame(PerformanceReport::SLOW_MS, PerformanceReport::slowLimit(), $bad);
            }
        } finally {
            putenv('MAUTIC_PERF_SLOW_MS');
        }
    }

    public function testOnlyTheTwentySlowestRequestsAreListedSlowestFirst(): void
    {
        $entries = [];
        foreach (range(1, 25) as $ms) {
            $entries[] = ['m' => 'GET', 'p' => '/x', 'ms' => $ms * 10, 'db' => 0, 'q' => 0];
        }
        $entries[] = ['m' => 'GET', 'p' => '/no-time'];

        $slowest = (new PerformanceReport())->build($entries)['slowest'];

        $this->assertCount(20, $slowest);
        $this->assertSame(250, $slowest[0]['ms']);
        $this->assertSame(60, $slowest[19]['ms']);
    }

    public function testASlowRequestIsOneAtTheLimitOrAbove(): void
    {
        $entries = array_merge($this->entries(1, 499.9, 0, 0), $this->entries(2, 500, 0, 0));

        $this->assertSame(2, (new PerformanceReport())->stats($entries, 500)['slow']);
    }

    public function testTheVerdictBoundaries(): void
    {
        $report = new PerformanceReport();
        $stats  = static fn (float $p95, float $share, float $queries): array => ['count' => 1, 'p95' => $p95, 'dbShare' => $share, 'avgQueries' => $queries];

        $this->assertSame(PerformanceReport::DB_SLOW_QUERIES, $report->verdict($stats(500.0, 0.6, 49.9), 500));
        $this->assertSame(PerformanceReport::DB_MANY_QUERIES, $report->verdict($stats(500.0, 0.6, 50.0), 500));
        $this->assertSame(PerformanceReport::HEALTHY, $report->verdict($stats(499.9, 0.9, 99.0), 500));
        $this->assertSame(PerformanceReport::APP, $report->verdict($stats(500.0, 0.3, 0.0), 500));
        $this->assertSame(PerformanceReport::MIXED, $report->verdict($stats(500.0, 0.31, 0.0), 500));
        $this->assertSame(PerformanceReport::MIXED, $report->verdict($stats(500.0, 0.59, 99.0), 500));
    }

    public function testTheAveragesAndTheRoundingOfTheStats(): void
    {
        $entries = [
            ['ms' => 300.19, 'db' => 100.19, 'q' => 3],
            ['ms' => 100.19, 'db' => 50.19, 'q' => 4],
            ['ms' => 200.19, 'db' => 100.19, 'q' => 4],
        ];
        $stats = (new PerformanceReport())->stats($entries);

        $this->assertSame(200.2, $stats['avg']);
        $this->assertSame(300.2, $stats['p95']);
        $this->assertSame(300.2, $stats['max']);
        $this->assertSame(83.5, $stats['avgDb']);
        $this->assertSame(3.7, $stats['avgQueries']);
        $this->assertSame(0.42, $stats['dbShare']);
    }

    public function testAnEntryWithoutMeasuresCountsAsZero(): void
    {
        $stats = (new PerformanceReport())->stats([['ms' => 100, 'db' => 10, 'q' => 4], []]);

        $this->assertSame(2, $stats['count']);
        $this->assertSame(50.0, $stats['avg']);
        $this->assertSame(5.0, $stats['avgDb']);
        $this->assertSame(2.0, $stats['avgQueries']);
        $this->assertSame(0, $stats['errors']);
    }

    public function testRequestsWithoutATimeAreTheLastOfTheSlowest(): void
    {
        $slowest = (new PerformanceReport())->build([['p' => 'none'], ['p' => 'fast', 'ms' => 1], ['p' => 'slow', 'ms' => 5]])['slowest'];

        $this->assertSame(['slow', 'fast', 'none'], array_column($slowest, 'p'));
    }

    public function testTheP95IsTheValueAtTheNinetyFifthPercentile(): void
    {
        $entries = [];
        foreach (range(20, 1) as $ms) {
            $entries[] = ['ms' => $ms, 'db' => 0, 'q' => 0];
        }

        $this->assertSame(19.0, (new PerformanceReport())->stats($entries)['p95']);
    }

    public function testTheP95RoundsTheRankUp(): void
    {
        $entries = [];
        foreach (range(1, 39) as $ms) {
            $entries[] = ['ms' => $ms];
        }

        $this->assertSame(38.0, (new PerformanceReport())->stats($entries)['p95'], '0.95 x 39 = 37.05: the 38th value');
    }

    public function testRequestsThatTookNoTimeHaveNoDatabaseShare(): void
    {
        $this->assertSame(0.0, (new PerformanceReport())->stats([['ms' => 0, 'db' => 5]])['dbShare']);
    }
}
