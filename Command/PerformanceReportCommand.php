<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Command;

use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console twin of the Performance screen: the API requests of the last hours
 * (read from the day files in var/logs/performance/), one row per route, and
 * where the time goes.
 */
#[AsCommand(name: 'mautic:performance:report', description: 'Shows how fast the API has been: per route, and whether the time goes to the database or to the application.')]
class PerformanceReportCommand extends Command
{
    public function __construct(private PerformanceLog $log, private PerformanceReport $report)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'How many hours back to look.', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours  = max(1, (int) $input->getOption('hours'));
        $slow   = PerformanceReport::slowLimit();
        $report = $this->report->build($this->log->read(time() - $hours * 3600), $slow);
        $total  = $report['total'];

        $output->writeln(sprintf('Last %dh: %d requests, avg %s ms, p95 %s ms, max %s ms, %d slow (>= %d ms), %d errors (5xx)', $hours, $total['count'], $total['avg'], $total['p95'], $total['max'], $total['slow'], $slow, $total['errors']));
        $output->writeln(sprintf('Time in SQL: %d%% of the total, %s queries per request.', (int) round($total['dbShare'] * 100), $total['avgQueries']));
        $output->writeln('Verdict: '.$report['verdict']);
        $output->writeln('');

        $rows = array_map(static fn (array $r): array => [$r['name'], $r['count'], $r['avg'], $r['p95'], $r['max'], $r['avgDb'], $r['avgQueries']], array_slice($report['routes'], 0, 25));
        (new \Symfony\Component\Console\Helper\Table($output))
            ->setHeaders(['Route', 'Requests', 'Avg ms', 'p95 ms', 'Max ms', 'Avg SQL ms', 'Avg queries'])
            ->setRows($rows)
            ->render();

        return Command::SUCCESS;
    }
}
