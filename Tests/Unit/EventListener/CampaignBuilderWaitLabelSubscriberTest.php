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

    public function testNoBalloonIsDrawnOnTheLine(): void
    {
        $this->assertStringNotContainsString('addOverlay', $this->content());
    }

    public function testPutsTheWaitInAStripOnTopOfTheNode(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('campaign-event-wait', $js);
        $this->assertStringContainsString('ri-time-line', $js);
        $this->assertStringContainsString('type.insertBefore(strip,type.firstChild)', $js);
        $this->assertStringContainsString('i.revalidate(n)', $js);
        $this->assertStringContainsString('requestAnimationFrame(', $js);
    }

    public function testSharesTheStripHelperAndSkipsAnUnchangedStrip(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('window.mauticPatchesSetWait=function(node,label)', $js);
        $this->assertStringContainsString('else if(strip.lastChild.textContent===label){return;}', $js);
    }

    public function testTheStripUsesTheThemeVariablesAndJoinsTheCard(): void
    {
        $this->assertStringContainsString('var(--layer-01)', CampaignBuilderWaitLabelSubscriber::STYLE);
        $this->assertStringContainsString('var(--text-secondary)', CampaignBuilderWaitLabelSubscriber::STYLE);
        $this->assertStringContainsString('.campaign-event-wait+.campaign-event-content{border-top-left-radius:0', CampaignBuilderWaitLabelSubscriber::STYLE);
    }

    public function testPatchesOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){patch();var result=original.apply(this,arguments);window.mauticPatchesApplyWaits();return result;}', $js);
        $this->assertStringContainsString('mQuery(patch);', $js);
        $this->assertStringContainsString('if(window.mauticPatchesWaitLabel){return;}', $js);
    }
}
