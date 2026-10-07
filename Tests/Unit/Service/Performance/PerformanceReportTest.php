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
}
