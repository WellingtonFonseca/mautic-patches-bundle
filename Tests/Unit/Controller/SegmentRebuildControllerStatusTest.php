<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use MauticPlugin\MauticPatchesBundle\Controller\SegmentRebuildController;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * statusAction only uses what it is handed, so the controller (which extends
 * core's CommonController, heavy to build) is created without its constructor.
 */
class SegmentRebuildControllerStatusTest extends TestCase
{
    private SegmentRebuilder&MockObject $rebuilder;

    private SegmentRebuildController $controller;

    protected function setUp(): void
    {
        $this->rebuilder  = $this->createMock(SegmentRebuilder::class);
        $this->controller = (new \ReflectionClass(SegmentRebuildController::class))->newInstanceWithoutConstructor();
    }

    public function testAnswersTheLastBuiltDateAsJson(): void
    {
        $this->rebuilder->method('lastBuilt')->with(7)->willReturn(['lastBuilt' => '2026-10-07T12:30:05+00:00']);

        $response = $this->controller->statusAction($this->rebuilder, 7);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"lastBuilt":"2026-10-07T12:30:05+00:00"}', $response->getContent());
    }

    public function testASegmentNeverBuiltAnswersNull(): void
    {
        $this->rebuilder->method('lastBuilt')->willReturn(['lastBuilt' => null]);

        $response = $this->controller->statusAction($this->rebuilder, 7);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"lastBuilt":null}', $response->getContent());
    }

    public function testNotFoundOrNotAllowedIsA404WithoutTellingWhich(): void
    {
        $this->rebuilder->method('lastBuilt')->with(7)->willReturn(null);

        $response = $this->controller->statusAction($this->rebuilder, 7);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"Segment not found."}', $response->getContent());
    }

    public function testAsksForTheIdItWasGiven(): void
    {
        $this->rebuilder->expects($this->once())->method('lastBuilt')->with(42)->willReturn(null);

        $this->controller->statusAction($this->rebuilder, 42);
    }
}
