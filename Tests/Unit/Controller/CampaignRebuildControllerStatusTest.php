<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use MauticPlugin\MauticPatchesBundle\Controller\CampaignRebuildController;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * statusAction only uses what it is handed, so the controller (which extends
 * core's CommonController, heavy to build) is created without its constructor.
 */
class CampaignRebuildControllerStatusTest extends TestCase
{
    private CampaignRebuilder&MockObject $rebuilder;

    private CampaignRebuildController $controller;

    protected function setUp(): void
    {
        $this->rebuilder  = $this->createMock(CampaignRebuilder::class);
        $this->controller = (new \ReflectionClass(CampaignRebuildController::class))->newInstanceWithoutConstructor();
    }

    public function testAnswersRunningAsJson(): void
    {
        $this->rebuilder->method('status')->with(7)->willReturn(['running' => true]);

        $response = $this->controller->statusAction($this->rebuilder, 7);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"running":true}', $response->getContent());
    }

    public function testAnswersNotRunning(): void
    {
        $this->rebuilder->method('status')->willReturn(['running' => false]);

        $this->assertSame('{"running":false}', $this->controller->statusAction($this->rebuilder, 7)->getContent());
    }

    public function testNotFoundOrNotAllowedIsA404WithoutTellingWhich(): void
    {
        $this->rebuilder->method('status')->with(7)->willReturn(null);

        $response = $this->controller->statusAction($this->rebuilder, 7);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"Campaign not found."}', $response->getContent());
    }
}
