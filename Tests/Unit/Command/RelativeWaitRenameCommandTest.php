<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use MauticPlugin\MauticPatchesBundle\Command\RelativeWaitRenameCommand;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:campaign-wait')]
#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class RelativeWaitRenameCommandTest extends TestCase
{
    private function createFormatter(): RelativeWaitNameFormatter
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $key, array $params = []): string {
                $count = $params['%count%'] ?? 0;
                // Simple translation logic for testing
                if ('mautic.patches.wait_relative.name.d' === $key) {
                    return 1 === $count ? 'Esperar 1 dia' : "Esperar {$count} dias";
                }
                if ('mautic.patches.wait_relative.name.w' === $key) {
                    return 1 === $count ? 'Esperar 1 semana' : "Esperar {$count} semanas";
                }

                return "Esperar {$count}";
            }
        );

        return new RelativeWaitNameFormatter($translator);
    }

    private function tester(array $rows, bool $expectUpdate = false): CommandTester
    {
        $formatter = $this->createFormatter();

        // Create a mock result
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($rows);

        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        if ($expectUpdate) {
            $connection->expects($this->any())->method('update');
        } else {
            $connection->expects($this->never())->method('update');
        }

        return new CommandTester(new RelativeWaitRenameCommand($connection, $formatter));
    }

    public function testDryRunListsChangesAndDoesNotUpdateDatabase(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Esperar', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])],
            ['id' => 2, 'name' => 'Old Wait', 'properties' => serialize(['amount' => 2, 'unit' => 'd'])],
        ];

        $tester  = $this->tester($rows, expectUpdate: false);
        $status  = $tester->execute(['--dry-run' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('dry run', strtolower($display));
        $this->assertStringContainsString('Old Wait', $display);
        $this->assertStringContainsString('Esperar 2 dias', $display);
    }

    public function testWithoutDryRunUpdatesDatabase(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Old Wait', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])],
        ];

        $formatter = $this->createFormatter();

        // Create a mock connection that tracks update calls
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);
        $connection->expects($this->once())->method('update')->with(
            'campaign_events',
            ['name' => 'Esperar 1 dia'],
            ['id' => 1]
        );

        $tester = new CommandTester(new RelativeWaitRenameCommand($connection, $formatter));
        $status = $tester->execute(['--dry-run' => false]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('renamed', strtolower($tester->getDisplay()));
    }

    public function testSkipsEventsWithUnreadableProperties(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Valid', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])],
            ['id' => 2, 'name' => 'Invalid', 'properties' => 'corrupted_data'],
            ['id' => 3, 'name' => 'Valid2', 'properties' => serialize(['amount' => 2, 'unit' => 'd'])],
        ];

        $tester  = $this->tester($rows, expectUpdate: false);
        $status  = $tester->execute(['--dry-run' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('unreadable', strtolower($display));
        $this->assertStringContainsString('skipped', strtolower($display));
    }

    public function testNoChangesNeeded(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Esperar 1 dia', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])],
            ['id' => 2, 'name' => 'Esperar 2 dias', 'properties' => serialize(['amount' => 2, 'unit' => 'd'])],
        ];

        $formatter = $this->createFormatter();

        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);
        $connection->expects($this->never())->method('update');

        $tester = new CommandTester(new RelativeWaitRenameCommand($connection, $formatter));
        $status = $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('0 of 2', $tester->getDisplay());
    }

    public function testEmptyEventTable(): void
    {
        $tester = $this->tester([]);
        $status = $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('0 of 0', $tester->getDisplay());
    }

    public function testSecondExecutionIsIdempotent(): void
    {
        $firstRunRows = [
            ['id' => 1, 'name' => 'Old Name', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])],
        ];

        $formatter = $this->createFormatter();

        // First run updates the database
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            $firstRunRows,
            [['id' => 1, 'name' => 'Esperar 1 dia', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])]],
        );

        $updateCallCount = 0;
        $connection->expects($this->any())->method('update')->willReturnCallback(
            function () use (&$updateCallCount) {
                ++$updateCallCount;
            }
        );

        $tester1 = new CommandTester(new RelativeWaitRenameCommand($connection, $formatter));
        $tester1->execute(['--dry-run' => false]);

        // Second run should not update
        $connection2 = $this->createMock(Connection::class);
        $connection2->method('fetchAllAssociative')->willReturn(
            [['id' => 1, 'name' => 'Esperar 1 dia', 'properties' => serialize(['amount' => 1, 'unit' => 'd'])]]
        );
        $connection2->expects($this->never())->method('update');

        $tester2 = new CommandTester(new RelativeWaitRenameCommand($connection2, $formatter));
        $status2 = $tester2->execute(['--dry-run' => false]);

        $this->assertSame(0, $status2);
        $this->assertStringContainsString('0 of 1', $tester2->getDisplay());
    }
}
