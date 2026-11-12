<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignActionsTabSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionFailureCounter;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEditPermission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CampaignActionsTabSubscriberTest extends TestCase
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    /** @var array<string, list<array<string, mixed>>> */
    private array $events = [
        'action'   => [
            ['id' => 3, 'name' => 'Enviar HSM', 'logCountProcessed' => 1, 'logCountForPending' => 1],
            ['id' => 5, 'name' => 'Esperar', 'logCountProcessed' => 0, 'logCountForPending' => 0],
        ],
        'decision' => [['id' => 4, 'name' => 'Abriu o email']],
    ];

    /**
     * @param array<int, int> $failed what the failure counter answers
     */
    private function subscriber(array $failed = [3 => 2], ?CorePermissions $security = null): CampaignActionsTabSubscriber
    {
        $counter = $this->createMock(CampaignActionFailureCounter::class);
        $counter->method('countByEvent')->willReturn($failed);

        return new CampaignActionsTabSubscriber($counter, new CampaignEditPermission($security ?? $this->createMock(CorePermissions::class)));
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame('inject', CampaignActionsTabSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_CONTENT]);
    }

    public function testTheTabLinkGoesInTheTabsOfTheCampaignPage(): void
    {
        $event = new CustomContentEvent(self::VIEW, 'tabs', ['events' => $this->events]);

        $this->subscriber()->inject($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates, 'the link only, never the pane in the list of tabs');
        $this->assertSame('@MauticPatches/Campaign/actions_tab_link.html.twig', $templates[0]['template']);
    }

    public function testThePaneListsOnlyTheActionStepsInTheirOrder(): void
    {
        $event = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $this->events]);

        $this->subscriber()->inject($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('@MauticPatches/Campaign/actions_tab_pane.html.twig', $templates[0]['template']);
        $this->assertSame($this->events['action'], $templates[0]['vars']['actions']);
        // 1 completed, 2 failed and 1 waiting: 25%, 50% and 25% of the 4 contacts that reached the step; a step nobody reached is all zero
        $this->assertSame(
            [3 => ['completed' => 25.0, 'failed' => 50.0, 'waiting' => 25.0], 5 => ['completed' => 0.0, 'failed' => 0.0, 'waiting' => 0.0]],
            $templates[0]['vars']['shares']
        );
    }

    public function testSharesAreRoundedToOneDecimal(): void
    {
        $events = ['action' => [['id' => 3, 'name' => 'a', 'logCountProcessed' => 1, 'logCountForPending' => 0]]];
        $event  = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $events]);

        $this->subscriber([3 => 2])->inject($event);

        $this->assertSame(['completed' => 33.3, 'failed' => 66.7, 'waiting' => 0.0], $event->getTemplates()[0]['vars']['shares'][3]);
    }

    /**
     * @param array<string, mixed> $vars
     *
     * @return list<int>
     */
    private function recalculateOf(array $vars, bool $canEdit, ?string $checkedOwner = '7'): array
    {
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')
            ->with('campaign:campaigns:editown', 'campaign:campaigns:editother', $checkedOwner)
            ->willReturn($canEdit);
        $event = new CustomContentEvent(self::VIEW, 'tabs.content', $vars);

        $this->subscriber([], $security)->inject($event);

        return $event->getTemplates()[0]['vars']['recalculate'];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitCampaignVars(): array
    {
        $campaign = new Campaign();
        $campaign->setCreatedBy('7');
        $actions = [
            ['id' => '3', 'name' => 'Enviar HSM', 'type' => 'email.send'],
            ['id' => '5', 'name' => 'Esperar', 'type' => RelativeWaitSubscriber::TYPE],
            ['id' => 9, 'name' => 'Esperar de novo', 'type' => RelativeWaitSubscriber::TYPE],
        ];

        return ['events' => ['action' => $actions], 'campaign' => $campaign];
    }

    public function testTheWaitStepsCanBeRecalculatedByWhoEditsTheCampaign(): void
    {
        // integer ids, only the Wait steps, in their order
        $this->assertSame([5, 9], $this->recalculateOf($this->waitCampaignVars(), true));
    }

    public function testNoStepCanBeRecalculatedWithoutTheEditAccessOfTheCampaign(): void
    {
        $this->assertSame([], $this->recalculateOf($this->waitCampaignVars(), false));
    }

    public function testNoStepCanBeRecalculatedWithoutTheCampaignInTheVars(): void
    {
        $vars = $this->waitCampaignVars();
        unset($vars['campaign']);

        $this->assertSame([], $this->recalculateOf($vars, true));
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function restrictionProvider(): iterable
    {
        yield 'a fixed hour' => ['triggerHour', '10:00'];
        yield 'a start hour' => ['triggerRestrictedStartHour', '10:00'];
        yield 'a stop hour' => ['triggerRestrictedStopHour', '16:00'];
        yield 'days of the week' => ['triggerRestrictedDaysOfWeek', [1, 2]];
    }

    /**
     * The pending contacts of a step with an hours / days restriction show as waiting too: core writes the same scheduled log for every pending contact.
     */
    #[DataProvider('restrictionProvider')]
    public function testThePendingOfARestrictedStepAreWaiting(string $key, mixed $value): void
    {
        $events = ['action' => [['id' => 3, 'name' => 'a', 'logCountProcessed' => 1, 'logCountForPending' => 3, $key => $value]]];
        $event  = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $events]);

        $this->subscriber([])->inject($event);

        $this->assertSame(['completed' => 25.0, 'failed' => 0.0, 'waiting' => 75.0], $event->getTemplates()[0]['vars']['shares'][3]);
    }

    public function testEmptyRestrictionsLeaveThePendingWaiting(): void
    {
        // what core gives a step with no restriction: nulls and an empty list of days
        $action = ['id' => 3, 'name' => 'a', 'logCountProcessed' => 0, 'logCountForPending' => 2, 'triggerHour' => null,
            'triggerRestrictedStartHour' => null, 'triggerRestrictedStopHour' => null, 'triggerRestrictedDaysOfWeek' => []];
        $event = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => ['action' => [$action]]]);

        $this->subscriber([])->inject($event);

        $this->assertSame(['completed' => 0.0, 'failed' => 0.0, 'waiting' => 100.0], $event->getTemplates()[0]['vars']['shares'][3]);
    }

    public function testAStepWithoutCoresCountsHasOnlyItsFailures(): void
    {
        $event = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => ['action' => [['id' => 3, 'name' => 'a']]]]);

        $this->subscriber([3 => 2])->inject($event);

        $this->assertSame(['completed' => 0.0, 'failed' => 100.0, 'waiting' => 0.0], $event->getTemplates()[0]['vars']['shares'][3]);
    }

    public function testCoresCountsMayComeAsText(): void
    {
        // the counts of core's events come from the database and can be strings
        $events = ['action' => [['id' => '3', 'name' => 'a', 'logCountProcessed' => '2', 'logCountForPending' => '1']]];
        $event  = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $events]);

        $this->subscriber([3 => 1])->inject($event);

        $this->assertSame(['completed' => 50.0, 'failed' => 25.0, 'waiting' => 25.0], $event->getTemplates()[0]['vars']['shares']['3']);
    }

    public function testAsksTheFailuresWithIntegerIds(): void
    {
        $counter = $this->createMock(CampaignActionFailureCounter::class);
        $counter->expects($this->once())->method('countByEvent')
            ->with($this->callback(static fn (array $ids): bool => [3, 5] === $ids))
            ->willReturn([]);
        // the ids of core's events can be strings
        $events = ['action' => [['id' => '3', 'name' => 'a'], ['id' => '5', 'name' => 'b']]];

        (new CampaignActionsTabSubscriber($counter, new CampaignEditPermission($this->createMock(CorePermissions::class))))->inject(new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $events]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function ignoredProvider(): array
    {
        return [
            'another view'         => ['@MauticEmail/Email/details.html.twig', 'tabs', ['events' => ['action' => [['id' => 1]]]]],
            'another context'      => [self::VIEW, 'details.stats.graph.below', ['events' => ['action' => [['id' => 1]]]]],
            'a campaign with no action' => [self::VIEW, 'tabs', ['events' => ['action' => [], 'decision' => [['id' => 1]]]]],
            'no events at all'     => [self::VIEW, 'tabs.content', []],
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    #[DataProvider('ignoredProvider')]
    public function testAddsNothingElsewhere(string $view, string $context, array $vars): void
    {
        $event = new CustomContentEvent($view, $context, $vars);

        $this->subscriber()->inject($event);

        $this->assertSame([], $event->getTemplates());
    }
}
