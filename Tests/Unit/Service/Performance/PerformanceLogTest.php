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

    public function testAnEntryWithoutATimeIsWrittenToday(): void
    {
        $log = new PerformanceLog($this->dir);
        $log->write(['ms' => 1.0]);

        $this->assertCount(1, glob($this->dir.'/*-'.date('Y-m-d').'.log') ?: []);
    }

    public function testTheFolderIsCreatedWithGroupWritePermission(): void
    {
        (new PerformanceLog($this->dir))->write(['t' => time(), 'ms' => 1.0]);

        clearstatcache();
        $this->assertSame(0775 & ~umask(), fileperms($this->dir) & 0777);
    }

    public function testTextIsKeptReadableInTheFile(): void
    {
        $log = new PerformanceLog($this->dir);
        $log->write(['t' => time(), 'p' => '/api/contatos/ação']);

        $this->assertStringContainsString('/api/contatos/ação', (string) file_get_contents(glob($this->dir.'/*')[0]));
    }

    public function testBlankLinesAndEntriesWithoutATimeAreIgnoredOnRead(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir.'/api-'.date('Y-m-d').'.log', "\n{\"ms\":1}\nnot json\n{\"t\":".time().",\"ms\":2}\n");

        $this->assertCount(1, (new PerformanceLog($this->dir))->read(time() - 3600));
    }

    public function testAFileOfTheCutoffMinuteIsKeptAndAnOlderOneIsDeleted(): void
    {
        mkdir($this->dir);
        $recent = $this->dir.'/api-2020-01-02.log';
        file_put_contents($recent, "{}\n");
        touch($recent, strtotime('-5 days'));

        (new PerformanceLog($this->dir))->write(['t' => time(), 'ms' => 1.0]);

        $this->assertFileExists($recent);
    }
}
