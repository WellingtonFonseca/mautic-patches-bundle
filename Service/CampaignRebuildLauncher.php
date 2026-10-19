<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Starts `mautic:campaigns:rebuild --campaign-id=<id>` in the background, the same
 * command the cron runs (it adds the contacts of the campaign's segments and
 * removes the ones that left), so the request does not wait for it.
 *
 * Unlike the segment command, this one does not save an end date, so the screen
 * cannot tell when it is done. A marker file stands for "running": it is created
 * before the command starts (the status answer is right from the first poll) and
 * the shell removes it when the command ends, whatever its exit code. A marker
 * older than MAX_AGE_SECONDS (a killed container) is ignored.
 *
 * Detached with `nohup ... &` because Symfony's Process kills its child when
 * the PHP request ends. Registered in Config/services.php, which gives it the
 * project dir and the marker folder (plain strings cannot be autowired).
 */
class CampaignRebuildLauncher
{
    public const MAX_AGE_SECONDS = 3600;

    private string $phpBinary;

    public function __construct(private string $projectDir, private string $markerDir, ?string $phpBinary = null)
    {
        $this->phpBinary = $phpBinary ?? ((new PhpExecutableFinder())->find() ?: 'php');
    }

    public function markerPath(int $campaignId): string
    {
        return rtrim($this->markerDir, '/').'/campaign-rebuild-'.$campaignId.'.running';
    }

    public function buildShellCommand(int $campaignId): string
    {
        $arguments = [
            $this->phpBinary,
            $this->projectDir.'/bin/console',
            'mautic:campaigns:rebuild',
            '--campaign-id='.$campaignId,
        ];
        $inner = implode(' ', array_map('escapeshellarg', $arguments)).'; rm -f '.escapeshellarg($this->markerPath($campaignId));

        return 'nohup sh -c '.escapeshellarg($inner).' >/dev/null 2>&1 &';
    }

    public function isRunning(int $campaignId): bool
    {
        $marker = $this->markerPath($campaignId);
        $time   = @filemtime($marker);

        return false !== $time && time() - $time < self::MAX_AGE_SECONDS;
    }

    /**
     * @throws \RuntimeException when the marker or the shell could not be started
     */
    public function launch(int $campaignId): void
    {
        $marker = $this->markerPath($campaignId);

        if (!is_dir($this->markerDir) && !@mkdir($this->markerDir, 0775, true) && !is_dir($this->markerDir)) {
            throw new \RuntimeException('Could not start the campaign update: the folder '.$this->markerDir.' cannot be created.');
        }

        if (false === @file_put_contents($marker, (string) time())) {
            throw new \RuntimeException('Could not start the campaign update: the file '.$marker.' cannot be written.');
        }

        $process = Process::fromShellCommandline($this->buildShellCommand($campaignId), $this->projectDir);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($marker);

            throw new \RuntimeException('Could not start the campaign update: '.trim($process->getErrorOutput()));
        }
    }
}
