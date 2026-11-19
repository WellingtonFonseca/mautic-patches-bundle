<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Command;

use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gives every existing Wait event the name its amount and unit give ("Esperar 2 dias"). `--dry-run` only lists "old -> new".
 * Writes campaign_events directly: back the table up first.
 */
#[AsCommand(name: 'mautic:patches:wait:rename', description: 'Renames the Wait events to "Esperar N unidade".')]
class RelativeWaitRenameCommand extends Command
{
    public function __construct(private Connection $connection, private RelativeWaitNameFormatter $formatter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list what would change.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prefix = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        $rows   = $this->connection->fetchAllAssociative(
            'SELECT id, name, properties FROM '.$prefix.'campaign_events WHERE type = ? AND deleted IS NULL ORDER BY id',
            [RelativeWaitSubscriber::TYPE]
        );

        $changed = 0;
        foreach ($rows as $row) {
            $properties = @unserialize((string) $row['properties'], ['allowed_classes' => false]);
            if (!is_array($properties)) {
                $output->writeln(sprintf('  event %d: properties unreadable, skipped', $row['id']));

                continue;
            }
            $new = $this->formatter->fromProperties($properties);
            if ($new === $row['name']) {
                continue;
            }

            ++$changed;
            $output->writeln(sprintf('  event %d: "%s" -> "%s"', $row['id'], $row['name'], $new));
            if (!$input->getOption('dry-run')) {
                $this->connection->update($prefix.'campaign_events', ['name' => $new], ['id' => $row['id']]);
            }
        }

        $output->writeln($input->getOption('dry-run')
            ? sprintf('Dry run: %d of %d Wait event(s) would change, nothing was written.', $changed, count($rows))
            : sprintf('%d of %d Wait event(s) renamed.', $changed, count($rows)));

        return Command::SUCCESS;
    }
}
