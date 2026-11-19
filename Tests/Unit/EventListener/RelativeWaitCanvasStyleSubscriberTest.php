<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitCanvasStyleSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('area:campaign-wait')]
#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class RelativeWaitCanvasStyleSubscriberTest extends TestCase
{
    public function testIsSubscribedToViewInjectCustomAssetsEvent(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $events = $subscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_ASSETS, $events);
    }

    public function testInjectsStylesWhenUrlContainsSlashS(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/s/campaigns/1/edit']);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with($this->callback(function (string $css): bool {
            return str_contains($css, 'patches.wait_relative') && str_contains($css, 'white-space: pre-line');
        }));

        $subscriber->injectAssets($event);
    }

    public function testDoesNotInjectStylesWhenUrlDoesNotContainSlashS(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/campaigns/1/edit']);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->never())->method('addStyleDeclaration');

        $subscriber->injectAssets($event);
    }

    public function testDoesNotInjectStylesWhenNoMainRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(null);

        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->never())->method('addStyleDeclaration');

        $subscriber->injectAssets($event);
    }

    public function testStyleContainsPreLineWhitespace(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/s/']);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with($this->callback(function (string $css): bool {
            return str_contains($css, 'white-space') && str_contains($css, 'pre-line');
        }));

        $subscriber->injectAssets($event);
    }

    public function testStyleSelectorContainsWaitRelativeClass(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/s/']);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $subscriber = new RelativeWaitCanvasStyleSubscriber($requestStack);
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with($this->stringContains('wait_relative'));

        $subscriber->injectAssets($event);
    }
}
