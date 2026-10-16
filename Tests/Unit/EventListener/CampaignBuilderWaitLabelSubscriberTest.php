<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignBuilderWaitLabelSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignBuilderWaitLabelSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignBuilderWaitLabelSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignBuilderWaitLabelSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testRemovesLabelsWithoutReadingLength(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Object.keys(overlays)', $js);
        $this->assertStringNotContainsString('overlays.length', $js);
        $this->assertStringContainsString('conn.removeOverlay(overlays[key].id)', $js);
    }

    public function testPatchesOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){patch();return original.apply(this,arguments);}', $js);
        $this->assertStringContainsString('mQuery(patch);', $js);
        $this->assertStringContainsString('if(window.mauticPatchesWaitLabel){return;}', $js);
    }
}
