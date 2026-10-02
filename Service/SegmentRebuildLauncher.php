<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Starts `mautic:segments:update --list-id=<id>` in the background, the same
 * command the cron runs, so the request does not wait for the rebuild.
 *
 * Going through the command (and not calling ListModel::rebuildListLeads()
 * directly) keeps what the command already does: its own lock per segment id
 * (a second rebuild of the same segment while one runs just exits with
 * "Script in progress"), batching, and saving the last built date/time.
 *
 * Detached with `nohup ... &` because Symfony's Process kills its child when
 * the PHP request ends. Registered in Config/services.php, which gives it
 * the project dir (a plain string cannot be autowired).
 */
class SegmentRebuildLauncher
{
    private string $phpBinary;

    public function __construct(private string $projectDir, ?string $phpBinary = null)
    {
        // PHP_BINARY is the web server's binary under mod_php, hence the finder.
        $this->phpBinary = $phpBinary ?? ((new PhpExecutableFinder())->find() ?: 'php');
    }

    public function buildShellCommand(int $segmentId): string
    {
        $arguments = [
            $this->phpBinary,
            $this->projectDir.'/bin/console',
            'mautic:segments:update',
            '--list-id='.$segmentId,
        ];

        return 'nohup '.implode(' ', array_map('escapeshellarg', $arguments)).' >/dev/null 2>&1 &';
    }

    /**
     * @throws \RuntimeException when the shell could not be started
     */
    public function launch(int $segmentId): void
    {
        $process = Process::fromShellCommandline($this->buildShellCommand($segmentId), $this->projectDir);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Could not start the segment rebuild: '.trim($process->getErrorOutput()));
        }
    }
}
