<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\Performance;

use Doctrine\DBAL\Logging\SQLLogger;
use MauticPlugin\MauticPatchesBundle\Service\Performance\QueryTimer;
use PHPUnit\Framework\TestCase;

class QueryTimerTest extends TestCase
{
    public function testCountsQueriesKeepsTheSlowestAndNeverTheParameters(): void
    {
        $timer = new QueryTimer();

        foreach (['SELECT 1', "SELECT\n  2", 'SELECT 3', 'SELECT 4'] as $sql) {
            $timer->startQuery($sql, ['secret@example.com']);
            usleep('SELECT 3' === $sql ? 5000 : 100);
            $timer->stopQuery();
        }

        $this->assertSame(4, $timer->count());
        $this->assertGreaterThan(5.0, $timer->totalMs());
        $this->assertCount(3, $timer->slowest());
        $this->assertSame('SELECT 3', $timer->slowest()[0]['sql']);
        $this->assertStringNotContainsString('secret', json_encode($timer->slowest()));
    }

    public function testTheLoggerThatWasThereKeepsGettingTheCalls(): void
    {
        $next = $this->createMock(SQLLogger::class);
        $next->expects($this->once())->method('startQuery');
        $next->expects($this->once())->method('stopQuery');

        $timer = new QueryTimer($next);
        $timer->startQuery('SELECT 1');
        $timer->stopQuery();
    }
}
