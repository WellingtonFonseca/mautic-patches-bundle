<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentDetailsButtonSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentDetailsButtonSubscriberTest extends TestCase
{
    private SegmentDetailsButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $route, array $p) => "/s/segments/{$p['objectAction']}/{$p['objectId']}");
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $this->subscriber = new SegmentDetailsButtonSubscriber($router, $translator);
    }

    private function event(string $location, string $route, mixed $item): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new CustomButtonEvent($location, $request, [], $item);
    }

    private function segment(): LeadList
    {
        $segment = $this->createStub(LeadList::class);
        $segment->method('getId')->willReturn(7);

        return $segment;
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, SegmentDetailsButtonSubscriber::getSubscribedEvents());
    }

    public function testAddsDetailsToTheRowMenuOfTheSegmentListFirst(): void
    {
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', $this->segment());

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.core.details]', $buttons[0]['btnText']);
        $this->assertSame('/s/segments/view/7', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertGreaterThan(150, $buttons[0]['priority'], 'above Edit and Update');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherPlaces(): array
    {
        return [
            'another list'    => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index'],
            'segment page'    => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_action'],
            'segment toolbar' => [ButtonHelper::LOCATION_TOOLBAR_ACTIONS, 'mautic_segment_index'],
        ];
    }

    #[DataProvider('otherPlaces')]
    public function testNothingElsewhere(string $location, string $route): void
    {
        $event = $this->event($location, $route, $this->segment());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNothingWhenTheItemIsNotASegment(): void
    {
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', ['id' => 7]);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }
}
