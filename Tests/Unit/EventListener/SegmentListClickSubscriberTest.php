<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentListClickSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentListClickSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('mautic_patches_segment_details', ['id' => 0])->willReturn('/s/segment-details/0');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $event = new CustomContentEvent('some_view', $context);
        (new SegmentListClickSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentListClickSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptKnowsTheModalRouteAndTheTitle(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('"/s/segment-details/"+m[1]', $js);
        $this->assertStringContainsString('"[mautic.patches.segment.details.title]"', $js);
        $this->assertStringContainsString('Mautic.ajaxifyModal(el,event);', $js);
    }

    public function testItRunsBeforeCoresAjaxLinkHandlerAndOnlyInTheSegmentList(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('},true);', $js, 'capture phase');
        $this->assertStringContainsString('"#page-list-wrapper a[href]"', $js);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $js);
    }
}
