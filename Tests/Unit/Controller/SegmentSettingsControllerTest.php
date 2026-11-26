<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\Controller\SegmentSettingsController;
use MauticPlugin\MauticPatchesBundle\Service\SegmentSettingsColumn;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/segment-settings/{id}: 404 when the segment is missing, 403 without view permission (unless the segment is visible to
 * others), otherwise the column's HTML.
 */
#[Group('area:segment')]
#[Group('risk:FLUXO')]
class SegmentSettingsControllerTest extends TestCase
{
    private function call(?LeadList $segment, bool $allowed): Response
    {
        $controller = new class() extends SegmentSettingsController {
            public function __construct()
            {
            }

            public function setSecurity(object $security): void
            {
                $this->security = $security;
            }

            public function notFound($msg = 'x')
            {
                return new Response('', 404);
            }

            public function accessDenied($batch = false, $msg = 'x')
            {
                return new Response('', 403);
            }
        };
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn($allowed);
        $controller->setSecurity($security);

        $model = $this->createMock(ListModel::class);
        $model->method('getEntity')->willReturn($segment);
        $column = $this->createMock(SegmentSettingsColumn::class);
        $column->method('html')->willReturn('<div>col</div>');

        return $controller->settingsAction($model, $column, 5);
    }

    public function testMissingSegmentIs404(): void
    {
        $this->assertSame(404, $this->call(null, true)->getStatusCode());
    }

    public function testPrivateSegmentWithoutViewPermissionIs403(): void
    {
        $segment = new LeadList();
        $segment->setIsGlobal(false);

        $this->assertSame(403, $this->call($segment, false)->getStatusCode());
    }

    public function testAllowedUserGetsTheColumnHtml(): void
    {
        $segment = new LeadList();
        $segment->setIsGlobal(false);
        $response = $this->call($segment, true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<div>col</div>', $response->getContent());
    }

    public function testSegmentVisibleToOthersNeedsNoPermissionCheck(): void
    {
        $segment = new LeadList();
        $segment->setIsGlobal(true);

        $this->assertSame(200, $this->call($segment, false)->getStatusCode());
    }
}
