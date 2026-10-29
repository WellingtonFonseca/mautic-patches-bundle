<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignBuilderWaitLabelSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignBuilderWaitLabelSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignBuilderWaitLabelSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTurnsTheInactionTextBackToTheWaitTextAfterAConditionOnly(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $rewrites = (new CampaignBuilderWaitLabelSubscriber($translator))->rewrites();

        $this->assertSame('mautic.campaign.connection.trigger.interval.label_inaction', $rewrites[0]['from']);
        $this->assertSame('mautic.campaign.connection.trigger.interval.label', $rewrites[0]['to']);
        $this->assertSame('mautic.campaign.connection.trigger.date.label_inaction', $rewrites[1]['from']);
        $js = CampaignBuilderWaitLabelSubscriber::script($rewrites);
        $this->assertStringContainsString('getAttribute("data-type")=="condition"', $js);
        $this->assertSame([], (new CampaignBuilderWaitLabelSubscriber())->rewrites());
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
        $this->assertStringContainsString('ri-hourglass-line', $js);
        $this->assertStringContainsString('type.insertBefore(strip,type.firstChild)', $js);
        $this->assertStringContainsString('i.revalidate(n)', $js);
        $this->assertStringContainsString('requestAnimationFrame(', $js);
    }

    public function testSharesTheStripHelperAndSkipsAnUnchangedStrip(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('window.mauticPatchesSetWait=function(node,label,lines)', $js);
        $this->assertStringContainsString('if(text.textContent!==label){text.textContent=label;changed=true;}', $js);
        $this->assertStringContainsString('if(changed){revalidate(node);}', $js);
    }

    public function testTheWaitLineHasTheHourglassAndTheScheduleLinesComeUnderIt(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('<i class="ri-hourglass-line"></i><span class="campaign-event-wait-text"></span>', $js);
        $this->assertStringNotContainsString('ri-time-line', $js, 'the clock is the icon of the hours line, which the formatter names');
        $this->assertStringContainsString('extra.className="campaign-event-wait-extra"', $js);
        $this->assertStringContainsString('row.className="campaign-event-wait-line"', $js);
        $this->assertStringContainsString('strip.appendChild(extra)', $js);
        // the lines are rebuilt only when they changed
        $this->assertStringContainsString('if(extra&&extra.getAttribute("data-lines")===sig){return false;}', $js);
        $this->assertStringContainsString('if(!lines.length){if(extra){extra.remove();return true;}return false;}', $js);
    }

    public function testTheBuilderAndThePreviewAskTheFormatterForTheLines(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('window.mauticPatchesScheduleApi', $js);
        $this->assertStringContainsString('scheduleOf(events[domEventId.replace("CampaignEvent_","")])', $js);
        $this->assertStringContainsString('scheduleOf(schedules[id])', $js);
        $this->assertStringContainsString('window.mauticPatchesPreviewSchedules', $js);
    }

    public function testTheLinesAreReadAgainAfterCoreStoresTheEditedEvent(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('var original=Mautic.campaignEventOnLoad;', $js);
        $this->assertStringContainsString('if(!original||original.mauticPatchesSchedule){return;}', $js);
        $this->assertStringContainsString('function patch(){patchEdit();', $js);
    }

    public function testTheStripIsAColumnOfLines(): void
    {
        $style = CampaignBuilderWaitLabelSubscriber::STYLE;

        $this->assertStringContainsString('.campaign-event-wait{display:flex;flex-direction:column;', $style);
        $this->assertStringContainsString('.campaign-event-wait-line{display:flex;align-items:center;gap:6px}', $style);
        $this->assertStringContainsString('.campaign-event-wait-extra{display:flex;flex-direction:column', $style);
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
