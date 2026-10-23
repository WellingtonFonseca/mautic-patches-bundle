<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignEventAccessTrait;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignWaitRecalculateController;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitRecalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The two endpoints behind the "Recalculate dates" button of the modal of a Wait node (Preview tab): the simulation (reads, answers
 * what would change) and the application (writes). The simulation needs the campaign's view permission like the modal itself;
 * applying needs its edit permission, and only a Wait node can be recalculated.
 */
class CampaignWaitRecalculateControllerTest extends TestCase
{
    /** @var RelativeWaitRecalculator&MockObject */
    private MockObject $recalculator;

    /** @var CorePermissions&MockObject */
    private MockObject $security;

    private ?Event $event;

    /** @var array<string, bool> the permissions the user has */
    private array $granted = ['campaign:campaigns:viewown' => true, 'campaign:campaigns:editown' => true];

    private const PLAN = ['rows' => [], 'summary' => ['total' => 5, 'changed' => 3, 'leaving_now' => 2, 'unchanged' => 0, 'without_date' => 0]];

    protected function setUp(): void
    {
        $this->recalculator = $this->createMock(RelativeWaitRecalculator::class);
        $this->security     = $this->createMock(CorePermissions::class);
        $this->security->method('hasEntityAccess')->willReturnCallback(fn (string $own): bool => $this->granted[$own] ?? false);
        $this->event = $this->waitEvent();
    }

    private function waitEvent(string $type = RelativeWaitSubscriber::TYPE): Event
    {
        $event = new Event();
        $event->setType($type);
        $event->setCampaign(new Campaign());

        return $event;
    }

    private function controller(): CampaignWaitRecalculateController
    {
        // core's controller needs ten services to be built: the double is made without its constructor
        $controller = (new \ReflectionClass(CampaignWaitRecalculateControllerDouble::class))->newInstanceWithoutConstructor();
        $controller->event = $this->event;
        (new \ReflectionProperty($controller, 'security'))->setValue($controller, $this->security);

        return $controller;
    }

    /** The status an action answers: Mautic 7 throws the 403 (AccessDeniedHttpException) where Mautic 5 returned it. */
    private function statusOf(callable $action): int
    {
        try {
            return $action()->getStatusCode();
        } catch (AccessDeniedHttpException) {
            return 403;
        }
    }

    private function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true);
    }

    public function testItUsesTheSharedRuleOfTheNodeModals(): void
    {
        $this->assertContains(CampaignEventAccessTrait::class, class_uses(CampaignWaitRecalculateController::class));
    }

    public function testTheSimulationAnswersWhatWouldChangeAndWritesNothing(): void
    {
        $this->recalculator->method('plan')->with($this->event)->willReturn(self::PLAN);
        $this->recalculator->expects($this->never())->method('apply');

        $response = $this->controller()->simulateAction($this->recalculator, 42);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['summary' => self::PLAN['summary'], 'canApply' => true], $this->json($response));
    }

    public function testWithoutEditPermissionTheSimulationCannotBeApplied(): void
    {
        $this->granted = ['campaign:campaigns:viewown' => true];
        $this->recalculator->method('plan')->willReturn(self::PLAN);

        $this->assertFalse($this->json($this->controller()->simulateAction($this->recalculator, 42))['canApply']);
    }

    public function testNothingToChangeCannotBeApplied(): void
    {
        $this->recalculator->method('plan')->willReturn(['rows' => [], 'summary' => ['total' => 5, 'changed' => 0, 'leaving_now' => 0, 'unchanged' => 5, 'without_date' => 0]]);

        $this->assertFalse($this->json($this->controller()->simulateAction($this->recalculator, 42))['canApply']);
    }

    public function testApplyingPlansAgainAndWritesTheDates(): void
    {
        $this->recalculator->method('plan')->with($this->event)->willReturn(self::PLAN);
        $this->recalculator->expects($this->once())->method('apply')->with(self::PLAN)->willReturn(5);

        $response = $this->controller()->applyAction($this->recalculator, 42);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['applied' => 5, 'summary' => self::PLAN['summary']], $this->json($response));
    }

    public function testApplyingNeedsTheEditPermission(): void
    {
        $this->granted = ['campaign:campaigns:viewown' => true];
        $this->recalculator->expects($this->never())->method('apply');

        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->applyAction($this->recalculator, 42)));
    }

    public function testNoViewPermissionIsA403ForTheSimulation(): void
    {
        $this->granted = [];
        $this->recalculator->expects($this->never())->method('plan');

        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->simulateAction($this->recalculator, 42)));
    }

    public function testAnUnknownEventIsA404(): void
    {
        $this->event = null;

        $this->assertSame(404, $this->controller()->simulateAction($this->recalculator, 42)->getStatusCode());
        $this->assertSame(404, $this->controller()->applyAction($this->recalculator, 42)->getStatusCode());
    }

    public function testOnlyAWaitNodeCanBeRecalculated(): void
    {
        $this->event = $this->waitEvent('email.send');
        $this->recalculator->expects($this->never())->method('plan');

        $this->assertSame(404, $this->controller()->simulateAction($this->recalculator, 42)->getStatusCode());
        $this->assertSame(404, $this->controller()->applyAction($this->recalculator, 42)->getStatusCode());
    }
}

/**
 * The controller with what it asks of core replaced: the event is given, the answers are plain responses.
 */
class CampaignWaitRecalculateControllerDouble extends CampaignWaitRecalculateController
{
    public ?Event $event = null;

    protected function findEvent(int $eventId): ?Event
    {
        return $this->event;
    }

    public function notFound($msg = 'mautic.core.url.error.404'): Response
    {
        return new Response('', 404);
    }

    /** On Mautic 7 core's accessDenied() throws the 403 (it returns an array only for a batch): the double does the same. */
    public function accessDenied($batch = false, $msg = 'mautic.core.url.error.401'): array
    {
        throw new AccessDeniedHttpException();
    }
}
