<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Doctrine\SegmentRepositoryListener;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentListRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SegmentRepositoryListenerTest extends TestCase
{
    public function testTheSegmentGetsTheRepositoryThatSortsByPrefix(): void
    {
        $metadata = new ClassMetadata(LeadList::class);

        (new SegmentRepositoryListener())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class)));

        $this->assertSame(SegmentListRepository::class, $metadata->customRepositoryClassName);
    }

    public function testOtherEntitiesAreLeftAlone(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);

        (new SegmentRepositoryListener())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class)));

        $this->assertNull($metadata->customRepositoryClassName);
    }
}
