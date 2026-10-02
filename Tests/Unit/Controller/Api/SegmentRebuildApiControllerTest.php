<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller\Api;

use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Controller\Api\SegmentRebuildApiController;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SegmentRebuildApiControllerTest extends TestCase
{
    private SegmentRebuilder&MockObject $rebuilder;

    private SegmentRebuildApiController $controller;

    protected function setUp(): void
    {
        $this->rebuilder  = $this->createMock(SegmentRebuilder::class);
        $this->controller = new SegmentRebuildApiController($this->rebuilder);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function statuses(): array
    {
        return [
            'not found'     => [SegmentRebuildResult::NOT_FOUND, 404],
            'forbidden'     => [SegmentRebuildResult::FORBIDDEN, 403],
            'not published' => [SegmentRebuildResult::NOT_PUBLISHED, 409],
            'failed'        => [SegmentRebuildResult::FAILED, 500],
        ];
    }

    /**
     * @dataProvider statuses
     */
    public function testErrorStatusesBecomeTheHttpCodeWithTheMessage(string $status, int $code): void
    {
        $this->rebuilder->method('request')->with(7)->willReturn(new SegmentRebuildResult($status, null, 'the message'));

        $response = $this->controller->rebuildAction(7);

        $this->assertSame($code, $response->getStatusCode());
        $this->assertSame(
            ['errors' => [['code' => $code, 'message' => 'the message']]],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testDispatchedIs202WithTheSegment(): void
    {
        $segment = $this->createMock(LeadList::class);
        $segment->method('getId')->willReturn(7);
        $segment->method('getName')->willReturn('Em andamento');
        $this->rebuilder->method('request')->willReturn(new SegmentRebuildResult(SegmentRebuildResult::DISPATCHED, $segment));

        $response = $this->controller->rebuildAction(7);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(
            ['segment' => ['id' => 7, 'name' => 'Em andamento'], 'status' => 'dispatched'],
            json_decode((string) $response->getContent(), true)
        );
    }
}
