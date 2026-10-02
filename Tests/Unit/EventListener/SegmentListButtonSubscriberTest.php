<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentListButtonSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentListButtonSubscriberTest extends TestCase
{
    private SegmentRebuilder&MockObject $rebuilder;

    private SegmentListButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            fn (string $route, array $p) => "/s/{$route}/{$p['id']}".(isset($p['return']) ? "?return={$p['return']}" : '')
        );
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $this->rebuilder  = $this->createMock(SegmentRebuilder::class);
        $this->subscriber = new SegmentListButtonSubscriber($router, $translator, $this->rebuilder);
    }

    private function event(string $location, string $route, mixed $item, ?string $objectAction = null): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        if (null !== $objectAction) {
            $request->attributes->set('objectAction', $objectAction);
        }

        return new CustomButtonEvent($location, $request, [], $item);
    }

    private function segment(): LeadList&MockObject
    {
        $segment = $this->createMock(LeadList::class);
        $segment->method('getId')->willReturn(7);

        return $segment;
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, SegmentListButtonSubscriber::getSubscribedEvents());
    }

    public function testAddsUpdateToTheRowMenuOfTheSegmentList(): void
    {
        $segment = $this->segment();
        $this->rebuilder->method('canRebuild')->with($segment)->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', $segment);

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.patches.segment.rebuild]', $buttons[0]['btnText']);
        $this->assertSame('/s/mautic_patches_segment_rebuild/7', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('POST', $buttons[0]['attr']['data-method']);
    }

    public function testAddsUpdateToThePageActionsOfTheSegmentDetailAndComesBackThere(): void
    {
        $segment = $this->segment();
        $this->rebuilder->method('canRebuild')->with($segment)->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_action', $segment, 'view');

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.patches.segment.rebuild]', $buttons[0]['btnText']);
        $this->assertSame('/s/mautic_patches_segment_rebuild/7?return=view', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('POST', $buttons[0]['attr']['data-method']);
    }

    public function testDetailButtonOnlyWhenTheUserCanRebuild(): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(false);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_action', $this->segment(), 'view');

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherSegmentActions(): array
    {
        return ['edit' => ['edit'], 'clone' => ['clone'], 'new' => ['new']];
    }

    /**
     * @dataProvider otherSegmentActions
     */
    public function testNoDetailButtonOnTheOtherSegmentScreens(string $action): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_action', $this->segment(), $action);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNoButtonWithoutEditAccessOrForAnUnpublishedSegment(): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(false);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', $this->segment());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherPlaces(): array
    {
        return [
            'another list'     => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index'],
            'segment toolbar'  => [ButtonHelper::LOCATION_TOOLBAR_ACTIONS, 'mautic_segment_index'],
            'segment index page bar'  => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_index'],
            'detail row menu'         => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_action'],
            'contact detail page bar' => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_action'],
        ];
    }

    /**
     * @dataProvider otherPlaces
     */
    public function testNothingElsewhere(string $location, string $route): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $event = $this->event($location, $route, $this->segment());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNothingWhenTheItemIsNotASegment(): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', ['id' => 7]);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }
}
