<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Event;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitRecalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gives the contacts that already wait at a Wait node the date its current rule gives them (see RelativeWaitRecalculator).
 * `--dry-run` only shows what would happen; `--list` adds each contact with its old and new date.
 */
#[AsCommand(name: 'mautic:patches:wait:recalculate', description: 'Recalculates the dates of the contacts waiting at a Wait node with its current rule.')]
class RelativeWaitRecalculateCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager, private RelativeWaitRecalculator $recalculator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event-id', null, InputOption::VALUE_REQUIRED, 'The id of the Wait event (campaign_events.id).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would change.')
            ->addOption('list', null, InputOption::VALUE_NONE, 'Also list each contact with its old and new date.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventId = (int) $input->getOption('event-id');
        $event   = $eventId > 0 ? $this->entityManager->getRepository(Event::class)->find($eventId) : null;

        if (!$event instanceof Event || !RelativeWaitSubscriber::isWait($event)) {
            $output->writeln('<error>--event-id must be the id of a Wait event (type '.RelativeWaitSubscriber::TYPE.').</error>');

            return Command::FAILURE;
        }

        $plan    = $this->recalculator->plan($event);
        $summary = $plan['summary'];

        $output->writeln(sprintf('Wait event %d, campaign %s', $eventId, $event->getCampaign()?->getName()));
        $output->writeln(sprintf('  %d contact(s) waiting', $summary['total']));
        $output->writeln(sprintf('  changed:      %d', $summary['changed']));
        $output->writeln(sprintf('  leaving now:  %d', $summary['leaving_now']));
        $output->writeln(sprintf('  unchanged:    %d', $summary['unchanged']));
        $output->writeln(sprintf('  without date: %d', $summary['without_date']));

        if ($input->getOption('list')) {
            foreach ($plan['rows'] as $row) {
                $output->writeln(sprintf('  contact %d: %s -> %s (%s)', $row['contact'], $row['old']->format(RelativeWaitRecalculator::FORMAT), $row['new']->format(RelativeWaitRecalculator::FORMAT), $row['status']));
            }
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('Dry run: nothing was changed. Run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('%d scheduled date(s) updated.', $this->recalculator->apply($plan)));

        return Command::SUCCESS;
    }
}
