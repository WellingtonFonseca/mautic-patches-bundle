<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Command;

use MauticPlugin\MauticPatchesBundle\Service\Performance\Diagnostics;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the timed probes of Service/Performance/Diagnostics.php (database
 * round trip, server load, MySQL settings, the Custom Object listing query)
 * and prints one line each. Exit code 1 when a probe fails.
 */
#[AsCommand(name: 'mautic:performance:diagnose', description: 'Timed checks of the database, the server and the Custom Object listing query.')]
class DiagnoseCommand extends Command
{
    public function __construct(private Diagnostics $diagnostics)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;

        foreach ($this->diagnostics->run() as $check) {
            $output->writeln(sprintf('[%-4s] %-12s %s', strtoupper($check['status']), $check['key'], $check['value']));
            $failed = $failed || Diagnostics::FAIL === $check['status'];
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
