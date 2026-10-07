<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\Performance;

use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use PHPUnit\Framework\TestCase;

class PerformanceLogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/perflog-'.uniqid();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
    }

    public function testWritesOneLinePerEntryAndReadsOnlyTheRequestedPeriod(): void
    {
        $log = new PerformanceLog($this->dir);
        $log->write(['t' => time() - 7200, 'ms' => 1.0]);
        $log->write(['t' => time() - 60, 'ms' => 2.0]);
        $log->write(['t' => time(), 'ms' => 3.0]);

        $this->assertCount(2, $log->read(time() - 3600));
        $this->assertCount(3, $log->read(time() - 86400));
    }

    public function testOldDayFilesAreDeletedWhenANewDayStarts(): void
    {
        mkdir($this->dir);
        $old = $this->dir.'/api-2020-01-01.log';
        file_put_contents($old, "{}\n");
        touch($old, strtotime('-30 days'));

        (new PerformanceLog($this->dir))->write(['t' => time(), 'ms' => 1.0]);

        $this->assertFileDoesNotExist($old);
    }

    public function testReadingWithNoFilesGivesNothing(): void
    {
        $this->assertSame([], (new PerformanceLog($this->dir))->read(time() - 3600));
    }
}
