<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\Stub;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\DTO\CampaignRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuilder;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuildLauncher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CampaignRebuilderTest extends TestCase
{
    private CampaignModel&MockObject $campaignModel;

    private CorePermissions&MockObject $security;

    private CampaignRebuildLauncher&MockObject $launcher;

    private CampaignRebuilder $rebuilder;

    protected function setUp(): void
    {
        $this->campaignModel = $this->createMock(CampaignModel::class);
        $this->security      = $this->createMock(CorePermissions::class);
        $this->launcher      = $this->createMock(CampaignRebuildLauncher::class);
        $this->rebuilder     = new CampaignRebuilder($this->campaignModel, $this->security, $this->launcher);
    }

    private function campaign(bool $published = true): Campaign&Stub
    {
        $campaign = $this->createStub(Campaign::class);
        $campaign->method('getId')->willReturn(7);
        $campaign->method('getName')->willReturn('Boas-vindas');
        $campaign->method('isPublished')->willReturn($published);
        $campaign->method('getCreatedBy')->willReturn(3);

        return $campaign;
    }

    public function testUnknownCampaign(): void
    {
        $this->campaignModel->method('getEntity')->with(7)->willReturn(null);
        $this->launcher->expects($this->never())->method('launch');

        $result = $this->rebuilder->request(7);

        $this->assertSame(CampaignRebuildResult::NOT_FOUND, $result->status);
        $this->assertNull($result->campaign);
    }

    public function testNoEditAccessChecksOwnAndOtherPermissionWithTheCreator(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign());
        $this->security->expects($this->once())->method('hasEntityAccess')
            ->with('campaign:campaigns:editown', 'campaign:campaigns:editother', 3)
            ->willReturn(false);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(CampaignRebuildResult::FORBIDDEN, $this->rebuilder->request(7)->status);
    }

    public function testUnpublishedCampaignIsNotUpdated(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign(false));
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(CampaignRebuildResult::NOT_PUBLISHED, $this->rebuilder->request(7)->status);
    }

    public function testAnUpdateAlreadyRunningIsNotStartedAgain(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('isRunning')->with(7)->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(CampaignRebuildResult::RUNNING, $this->rebuilder->request(7)->status);
    }

    public function testDispatched(): void
    {
        $campaign = $this->campaign();
        $this->campaignModel->method('getEntity')->willReturn($campaign);
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('isRunning')->willReturn(false);
        $this->launcher->expects($this->once())->method('launch')->with(7);

        $result = $this->rebuilder->request(7);

        $this->assertSame(CampaignRebuildResult::DISPATCHED, $result->status);
        $this->assertSame($campaign, $result->campaign);
    }

    public function testLauncherFailure(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('launch')->willThrowException(new \RuntimeException('boom'));

        $result = $this->rebuilder->request(7);

        $this->assertSame(CampaignRebuildResult::FAILED, $result->status);
        $this->assertSame('boom', $result->message);
    }

    public function testCanRebuildIsTheSameCheckWithoutLaunching(): void
    {
        $this->security->method('hasEntityAccess')->with('campaign:campaigns:editown', 'campaign:campaigns:editother', 3)->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertTrue($this->rebuilder->canRebuild($this->campaign()));
        $this->assertFalse($this->rebuilder->canRebuild($this->campaign(false)));
    }

    public function testStatusIsNullForAnUnknownCampaign(): void
    {
        $this->campaignModel->method('getEntity')->with(7)->willReturn(null);

        $this->assertNull($this->rebuilder->status(7));
    }

    public function testStatusIsNullWithoutEditAccessSoItDoesNotTellIfTheCampaignExists(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign());
        $this->security->method('hasEntityAccess')->willReturn(false);

        $this->assertNull($this->rebuilder->status(7));
    }

    public function testStatusSaysWhetherTheUpdateIsRunning(): void
    {
        $this->campaignModel->method('getEntity')->willReturn($this->campaign());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('isRunning')->with(7)->willReturnOnConsecutiveCalls(true, false);

        $this->assertSame(['running' => true], $this->rebuilder->status(7));
        $this->assertSame(['running' => false], $this->rebuilder->status(7));
    }
}
