<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixValue;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue;
use MauticPlugin\MauticPatchesBundle\MauticPatchesBundle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * onPluginUpdate creates the prefix tables on the version bump that introduced them, only when missing.
 * The table creation itself is core's installPluginSchema, which starts by asking the factory for the
 * database: a factory that throws there tells that the creation was attempted.
 */
class MauticPatchesBundleTest extends TestCase
{
    private MauticFactory&MockObject $factory;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(MauticFactory::class);
    }

    public function testTakesMauticFactoryAsCoreCallsIt(): void
    {
        $factory = (new \ReflectionMethod(MauticPatchesBundle::class, 'onPluginUpdate'))->getParameters()[1];

        $this->assertSame(MauticFactory::class, (string) $factory->getType());
        $this->assertFalse($factory->isOptional());
    }

    public function testCreatesNothingWhenBothTablesExist(): void
    {
        $schema = $this->createMock(Schema::class);
        $schema->method('hasTable')->willReturn(true);
        $this->factory->expects($this->never())->method('getDatabase');

        MauticPatchesBundle::onPluginUpdate(new Plugin(), $this->factory, $this->metadata(), $schema);
    }

    public function testCreatesNothingWithoutMetadata(): void
    {
        $this->factory->expects($this->never())->method('getDatabase');

        MauticPatchesBundle::onPluginUpdate(new Plugin(), $this->factory, null, null);
    }

    public function testCreatesTheSegmentTableWhenMissing(): void
    {
        $schema = $this->createMock(Schema::class);
        $schema->method('hasTable')->willReturnCallback(static fn (string $table): bool => SegmentPrefixValue::TABLE_NAME !== $table);
        $this->factory->expects($this->once())->method('getDatabase')->willThrowException(new \RuntimeException('create attempted'));

        $this->expectExceptionMessage('create attempted');
        MauticPatchesBundle::onPluginUpdate(new Plugin(), $this->factory, $this->metadata(), $schema);
    }

    public function testCreatesTheCampaignTableWhenMissing(): void
    {
        $schema = $this->createMock(Schema::class);
        $schema->method('hasTable')->willReturnCallback(static fn (string $table): bool => CampaignPrefixValue::TABLE_NAME !== $table);
        $this->factory->expects($this->once())->method('getDatabase')->willThrowException(new \RuntimeException('create attempted'));

        $this->expectExceptionMessage('create attempted');
        MauticPatchesBundle::onPluginUpdate(new Plugin(), $this->factory, $this->metadata(), $schema);
    }

    public function testCreatesBothWhenThereIsNoInstalledSchema(): void
    {
        $this->factory->expects($this->once())->method('getDatabase')->willThrowException(new \RuntimeException('create attempted'));

        $this->expectExceptionMessage('create attempted');
        MauticPatchesBundle::onPluginUpdate(new Plugin(), $this->factory, $this->metadata(), null);
    }

    /**
     * @return array<string, ClassMetadata<object>>
     */
    private function metadata(): array
    {
        return [
            SegmentPrefixValue::class  => $this->createMock(ClassMetadata::class),
            CampaignPrefixValue::class => $this->createMock(ClassMetadata::class),
        ];
    }
}
