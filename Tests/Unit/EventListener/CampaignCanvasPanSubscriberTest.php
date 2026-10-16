<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignCanvasPanSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignCanvasPanSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignCanvasPanSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignCanvasPanSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testOnlyTheMiddleButtonOnTheCanvasStartsIt(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('if(e.button!==1||!onCanvas(e)){return;}', $js);
        $this->assertStringContainsString('closest("#campaign-builder")', $js);
    }

    public function testTheBrowsersAutoScrollAndTheNewTabAreCancelled(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('e.preventDefault();', $js);
        $this->assertStringContainsString('document.addEventListener("auxclick"', $js);
    }

    public function testTheCursorIsTheClosedHandWhileDragging(): void
    {
        $this->assertStringContainsString('cursor:grabbing!important', CampaignCanvasPanSubscriber::STYLE);
        $this->assertStringContainsString('#campaign-builder *', CampaignCanvasPanSubscriber::STYLE);
    }

    public function testRunsOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesCanvasPan){return;}', $this->content());
    }
}
