<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller\Api;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\Controller\Api\SegmentRebuildApiController;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SegmentRebuildApiControllerTest extends TestCase
{
    private ListModel&MockObject $listModel;

    private CorePermissions&MockObject $security;

    private SegmentRebuildLauncher&MockObject $launcher;

    private SegmentRebuildApiController $controller;

    protected function setUp(): void
    {
        $this->listModel  = $this->createMock(ListModel::class);
        $this->security   = $this->createMock(CorePermissions::class);
        $this->launcher   = $this->createMock(SegmentRebuildLauncher::class);
        $this->controller = new SegmentRebuildApiController($this->listModel, $this->security, $this->launcher);
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

    public function testUnknownSegmentIs404AndNothingIsLaunched(): void
    {
        $this->listModel->method('getEntity')->with(7)->willReturn(null);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(404, $this->controller->rebuildAction(7)->getStatusCode());
    }

    public function testWithoutEditPermissionIs403AndNothingIsLaunched(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->expects($this->once())->method('hasEntityAccess')
            ->with('lead:lists:editown', 'lead:lists:editother', 3)
            ->willReturn(false);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(403, $this->controller->rebuildAction(7)->getStatusCode());
    }

    public function testUnpublishedSegmentIs409BecauseTheCommandSkipsIt(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment(false));
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->expects($this->never())->method('launch');

        $this->assertSame(409, $this->controller->rebuildAction(7)->getStatusCode());
    }

    public function testLaunchesTheRebuildAndAnswers202WithoutWaiting(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->expects($this->once())->method('launch')->with(7);

        $response = $this->controller->rebuildAction(7);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(
            ['segment' => ['id' => 7, 'name' => 'Em andamento'], 'status' => 'dispatched'],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testFailureToStartIs500(): void
    {
        $this->listModel->method('getEntity')->willReturn($this->segment());
        $this->security->method('hasEntityAccess')->willReturn(true);
        $this->launcher->method('launch')->willThrowException(new \RuntimeException('boom'));

        $response = $this->controller->rebuildAction(7);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('boom', (string) $response->getContent());
    }
}
