<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Doctrine\CampaignRepositoryListener;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CampaignRepositoryListenerTest extends TestCase
{
    public function testTheCampaignGetsTheRepositoryThatSortsByPrefix(): void
    {
        $metadata = new ClassMetadata(Campaign::class);

        (new CampaignRepositoryListener())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class)));

        $this->assertSame(CampaignPrefixRepository::class, $metadata->customRepositoryClassName);
    }

    public function testOtherEntitiesAreLeftAlone(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);

        (new CampaignRepositoryListener())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class)));

        $this->assertNull($metadata->customRepositoryClassName);
    }
}
