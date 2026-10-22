<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use MauticPlugin\MauticPatchesBundle\Command\RelativeWaitRecalculateCommand;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitRecalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RelativeWaitRecalculateCommandTest extends TestCase
{
    /** @var RelativeWaitRecalculator&MockObject */
    private MockObject $recalculator;

    private ?Event $event;

    private function plan(): array
    {
        $now = new \DateTimeImmutable('2026-03-10 12:00:00', new \DateTimeZone('UTC'));

        return [
            'rows'    => [
                ['log' => 101, 'contact' => 1, 'old' => $now->modify('+10 days'), 'new' => $now->modify('+15 days'), 'status' => 'changed', 'without_date' => false],
                ['log' => 102, 'contact' => 2, 'old' => $now->modify('+10 days'), 'new' => $now, 'status' => 'now', 'without_date' => false],
            ],
            'summary' => ['total' => 2, 'changed' => 1, 'leaving_now' => 1, 'unchanged' => 0, 'without_date' => 0],
        ];
    }

    private function tester(?Event $event): CommandTester
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($event);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(Event::class)->willReturn($repository);

        return new CommandTester(new RelativeWaitRecalculateCommand($manager, $this->recalculator));
    }

    private function waitEvent(string $type = RelativeWaitSubscriber::TYPE): Event
    {
        $event = $this->createMock(Event::class);
        $event->method('getType')->willReturn($type);
        $event->method('getCampaign')->willReturn($this->createMock(Campaign::class));

        return $event;
    }

    protected function setUp(): void
    {
        $this->recalculator = $this->createMock(RelativeWaitRecalculator::class);
        $this->recalculator->method('plan')->willReturn($this->plan());
    }

    public function testDryRunShowsTheNumbersAndChangesNothing(): void
    {
        $this->recalculator->expects($this->never())->method('apply');
        $tester = $this->tester($this->waitEvent());

        $status = $tester->execute(['--event-id' => '42', '--dry-run' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('2', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/dry run/i', $tester->getDisplay());
        $this->assertStringContainsString('changed', $tester->getDisplay());
        $this->assertStringContainsString('leaving now', $tester->getDisplay());
    }

    public function testWithoutDryRunItApplies(): void
    {
        $this->recalculator->expects($this->once())->method('apply')->with($this->plan())->willReturn(2);
        $tester = $this->tester($this->waitEvent());

        $this->assertSame(0, $tester->execute(['--event-id' => '42']));
        $this->assertStringContainsString('2 scheduled date(s) updated', $tester->getDisplay());
    }

    public function testListShowsEachContactWithItsOldAndNewDate(): void
    {
        $tester = $this->tester($this->waitEvent());

        $tester->execute(['--event-id' => '42', '--dry-run' => true, '--list' => true]);

        $this->assertStringContainsString('2026-03-20 12:00:00', $tester->getDisplay());
        $this->assertStringContainsString('2026-03-25 12:00:00', $tester->getDisplay());
    }

    public function testAnUnknownEventOrAnotherActionFails(): void
    {
        $this->recalculator->expects($this->never())->method('apply');

        $this->assertSame(1, $this->tester(null)->execute(['--event-id' => '42']));
        $this->assertSame(1, $this->tester($this->waitEvent('email.send'))->execute(['--event-id' => '42']));
    }

    public function testTheEventIdIsRequired(): void
    {
        $this->assertSame(1, $this->tester($this->waitEvent())->execute([]));
    }
}
