<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentInChipsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class SegmentInChipsSubscriberTest extends TestCase
{
    private function content(RouterInterface $router, string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentInChipsSubscriber($router))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheScriptCarriesTheComponentAndBothRoutes(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['mautic_patches_segment_filter_values', [], RouterInterface::ABSOLUTE_PATH, '/s/segment-filter-values'],
            ['mautic_custom_segment_filter_values', [], RouterInterface::ABSOLUTE_PATH, '/s/custom/segment-filter-values.json'],
        ]);

        $content = $this->content($router);

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesInChips', $content);
        $this->assertStringContainsString('"valuesUrl":"/s/segment-filter-values"', $content);
        $this->assertStringContainsString('"customUrl":"/s/custom/segment-filter-values.json"', $content);
    }

    public function testWithoutTheCustomObjectsPluginThereIsNoCustomUrl(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $name) {
            if ('mautic_custom_segment_filter_values' === $name) {
                throw new RouteNotFoundException();
            }

            return '/s/segment-filter-values';
        });

        $this->assertStringContainsString('"customUrl":null', $this->content($router));
    }

    public function testOtherPlacesGetNothing(): void
    {
        $this->assertSame('', $this->content($this->createMock(RouterInterface::class), 'page.header.right'));
    }

    public function testSubscribesToTheCustomContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentInChipsSubscriber::getSubscribedEvents());
    }
}
