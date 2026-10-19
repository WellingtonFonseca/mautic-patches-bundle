<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuildLauncher;
use PHPUnit\Framework\TestCase;

class CampaignRebuildLauncherTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/campaign-rebuild-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testShellCommandRunsTheCampaignsCommandForThatCampaignOnlyAndRemovesTheMarkerAtTheEnd(): void
    {
        $launcher = new CampaignRebuildLauncher('/var/www/html', '/m', '/usr/local/bin/php');

        $this->assertSame(
            "nohup sh -c ''\\''/usr/local/bin/php'\\'' '\\''/var/www/html/bin/console'\\'' '\\''mautic:campaigns:rebuild'\\'' '\\''--campaign-id=7'\\''; rm -f '\\''/m/campaign-rebuild-7.running'\\''' >/dev/null 2>&1 &",
            $launcher->buildShellCommand(7)
        );
    }

    public function testCommandIsDetachedSoTheRequestDoesNotWaitForIt(): void
    {
        $command = (new CampaignRebuildLauncher('/p', '/m', '/php'))->buildShellCommand(1);

        $this->assertStringStartsWith('nohup ', $command);
        $this->assertStringEndsWith(' &', $command);
        $this->assertStringContainsString('>/dev/null 2>&1', $command);
    }

    public function testDoesNotUseTheCommandLockBypass(): void
    {
        $command = (new CampaignRebuildLauncher('/p', '/m', '/php'))->buildShellCommand(1);

        $this->assertStringNotContainsString('bypass-locking', $command);
        $this->assertStringNotContainsString('--force', $command);
    }

    public function testNotRunningWithoutAMarker(): void
    {
        $this->assertFalse((new CampaignRebuildLauncher('/p', $this->dir, '/php'))->isRunning(7));
    }

    public function testRunningWhileTheMarkerIsFresh(): void
    {
        $launcher = new CampaignRebuildLauncher('/p', $this->dir, '/php');
        mkdir($this->dir);
        touch($launcher->markerPath(7));

        $this->assertTrue($launcher->isRunning(7));
        $this->assertFalse($launcher->isRunning(8), 'the marker is per campaign');
    }

    public function testAStaleMarkerIsIgnored(): void
    {
        $launcher = new CampaignRebuildLauncher('/p', $this->dir, '/php');
        mkdir($this->dir);
        touch($launcher->markerPath(7), time() - CampaignRebuildLauncher::MAX_AGE_SECONDS - 5);

        $this->assertFalse($launcher->isRunning(7));
    }

    public function testTheShellRemovesTheMarkerWhenTheCommandEnds(): void
    {
        // a fake "php" that is just true: the shell runs it, then removes the marker
        $launcher = new CampaignRebuildLauncher(sys_get_temp_dir(), $this->dir, '/bin/true');

        $launcher->launch(7);

        $this->waitUntilGone($launcher->markerPath(7));
        $this->assertFalse($launcher->isRunning(7));
    }

    private function waitUntilGone(string $file): void
    {
        for ($i = 0; $i < 50 && file_exists($file); ++$i) {
            usleep(100000);
        }
        $this->assertFileDoesNotExist($file);
    }
}
