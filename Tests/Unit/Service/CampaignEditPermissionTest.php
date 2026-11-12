<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEditPermission;
use PHPUnit\Framework\TestCase;

class CampaignEditPermissionTest extends TestCase
{
    private function campaignOf(int $owner): Campaign
    {
        $campaign = $this->createStub(Campaign::class);
        $campaign->method('getCreatedBy')->willReturn($owner);

        return $campaign;
    }

    public function testAsksTheEditOwnAndEditOtherAccessForTheCampaignsOwner(): void
    {
        $security = $this->createMock(CorePermissions::class);
        $security->expects($this->once())->method('hasEntityAccess')
            ->with('campaign:campaigns:editown', 'campaign:campaigns:editother', 7)
            ->willReturn(true);

        $this->assertTrue((new CampaignEditPermission($security))->allows($this->campaignOf(7)));
    }

    public function testNoEditAccessIsRefused(): void
    {
        $security = $this->createStub(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn(false);

        $this->assertFalse((new CampaignEditPermission($security))->allows($this->campaignOf(7)));
    }
}
