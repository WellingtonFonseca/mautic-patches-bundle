<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SegmentRebuilderTest extends TestCase
{
    private ListModel&MockObject $listModel;

    private CorePermissions&MockObject $security;

    private SegmentRebuildLauncher&MockObject $launcher;

    private SegmentRebuilder $rebuilder;

    protected function setUp(): void
    {
        $this->listModel = $this->createMock(ListModel::class);
        $this->security  = $this->createMock(CorePermissions::class);
        $this->launcher  = $this->createMock(SegmentRebuildLauncher::class);
        $this->rebuilder = new SegmentRebuilder($this->listModel, $this->security, $this->launcher);
    }

    private function segment(bool $published = true): LeadList&MockObject
    {
        $segment = $this->createMock(LeadList::class);
        $segment->method('getId')->willReturn(7);
        $segment->method('getName')->willReturn('Em andamento');
        $segment->method('isPublished')->willReturn($published);
        $segment->method('getCreatedBy')->willReturn(3);

        return $segment;
    }

    public function testUnknownSegment(): void
    {
        $this->listModel->method('getEntity')->with(7)->willReturn(null);
        $this->launcher->expects($this->never())->method('launch');

        $result = $this->rebuilder->request(7);

        $this->assertSame(SegmentRebuildResult::NOT_FOUND, $result->status);
        $this->assertNull($result->segment);
        $this->assertStringContainsString('7', $result->message);
    }

    public function testNoEditAccessChecksOwnAndOtherPermissionWithTheCreator(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->expects($this->once())->method('hasEntityAccess')
            ->with('lead:lists:editown', 'lead:lists:editother', 3)
            ->willReturn(false);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(SegmentRebuildResult::FORBIDDEN, $this->rebuilder->request(7)->status);
    }

    public function testUnpublishedSegmentIsNotRebuilt(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment(false));
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(SegmentRebuildResult::NOT_PUBLISHED, $this->rebuilder->request(7)->status);
    }

    public function testDispatched(): void
    {
        $segment = $this->segment();
        $this->listModel->method('getEntity')->willReturn($segment);
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->expects($this->once())->method('launch')->with(7);

        $result = $this->rebuilder->request(7);

        $this->assertSame(SegmentRebuildResult::DISPATCHED, $result->status);
        $this->assertSame($segment, $result->segment);
    }

    public function testLauncherFailure(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('launch')->willThrowException(new \RuntimeException('boom'));

        $result = $this->rebuilder->request(7);

        $this->assertSame(SegmentRebuildResult::FAILED, $result->status);
        $this->assertSame('boom', $result->message);
    }

    public function testCanRebuildIsTheSameCheckWithoutLaunching(): void
    {
        $this->security->method('hasEntityAccess')->with('lead:lists:editown', 'lead:lists:editother', 3)->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertTrue($this->rebuilder->canRebuild($this->segment()));
        $this->assertFalse($this->rebuilder->canRebuild($this->segment(false)));
    }

    public function testLastBuiltIsNullForAnUnknownSegment(): void
    {
        $this->listModel->method('getEntity')->with(7)->willReturn(null);

        $this->assertNull($this->rebuilder->lastBuilt(7));
    }

    public function testLastBuiltIsNullWithoutEditAccessSoItDoesNotTellIfTheSegmentExists(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->method('hasEntityAccess')->willReturn(false);

        $this->assertNull($this->rebuilder->lastBuilt(7));
    }

    public function testLastBuiltIsTheDateWhenTheRebuildEnded(): void
    {
        $segment = $this->segment();
        $segment->method('getLastBuiltDate')->willReturn(new \DateTime('2026-10-07 12:30:05', new \DateTimeZone('UTC')));
        $this->listModel->method('getEntity')->willReturn($segment);
        $this->security->method('hasEntityAccess')->willReturn(true);

        $this->assertSame(['lastBuilt' => '2026-10-07T12:30:05+00:00'], $this->rebuilder->lastBuilt(7));
    }

    public function testLastBuiltIsNullInsideTheAnswerForASegmentNeverBuilt(): void
    {
        $segment = $this->segment();
        $segment->method('getLastBuiltDate')->willReturn(null);
        $this->listModel->method('getEntity')->willReturn($segment);
        $this->security->method('hasEntityAccess')->willReturn(true);

        $this->assertSame(['lastBuilt' => null], $this->rebuilder->lastBuilt(7));
    }
}
