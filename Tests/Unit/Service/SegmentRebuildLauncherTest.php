<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use PHPUnit\Framework\TestCase;

class SegmentRebuildLauncherTest extends TestCase
{
    public function testShellCommandRunsTheSegmentsCommandForThatSegmentOnly(): void
    {
        $launcher = new SegmentRebuildLauncher('/var/www/html', '/usr/local/bin/php');

        $this->assertSame(
            "nohup '/usr/local/bin/php' '/var/www/html/bin/console' 'mautic:segments:update' '--list-id=7' >/dev/null 2>&1 &",
            $launcher->buildShellCommand(7)
        );
    }

    public function testCommandIsDetachedSoTheRequestDoesNotWaitForIt(): void
    {
        $command = (new SegmentRebuildLauncher('/p', '/php'))->buildShellCommand(1);

        $this->assertStringStartsWith('nohup ', $command);
        $this->assertStringEndsWith(' &', $command);
        $this->assertStringContainsString('>/dev/null 2>&1', $command);
    }

    public function testPathsAreQuotedAgainstShellInjection(): void
    {
        $command = (new SegmentRebuildLauncher("/p'; rm -rf /; '", '/php'))->buildShellCommand(1);

        // every quote of the path is closed, escaped and reopened: the text stays inside one shell word
        $this->assertStringContainsString("'/p'\\''; rm -rf /; '\\''/bin/console'", $command);
    }

    public function testDoesNotUseTheCommandLockBypass(): void
    {
        // the command's own per-segment lock must keep two rebuilds of the same segment from overlapping
        $command = (new SegmentRebuildLauncher('/p', '/php'))->buildShellCommand(1);

        $this->assertStringNotContainsString('bypass-locking', $command);
        $this->assertStringNotContainsString('--force', $command);
    }
}
