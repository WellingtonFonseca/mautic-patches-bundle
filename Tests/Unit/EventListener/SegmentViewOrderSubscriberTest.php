<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentViewOrderSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentViewOrderSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentViewOrderSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentViewOrderSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testMovesTheChartAfterTheContactsTabs(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#contacts-container").closest(".tab-content")', $js);
        $this->assertStringContainsString('mQuery(".ri-line-chart-fill").first().closest(".pa-md")', $js);
        $this->assertStringContainsString('$tabs.after($chart)', $js);
    }

    public function testOnlyActsOnTheSegmentPageWithTheChartOutsideTheTabs(): void
    {
        $this->assertStringContainsString('if(!$tabs.length||!$chart.length||$tabs[0].contains($chart[0])){return;}', $this->content());
    }

    public function testDoesNothingWhenAlreadyBelowTheContacts(): void
    {
        $this->assertStringContainsString('compareDocumentPosition($tabs[0])&Node.DOCUMENT_POSITION_PRECEDING', $this->content());
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;}', $js);
        $this->assertStringContainsString('mQuery(reorder);', $js);
    }

    public function testInstallsOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesSegmentViewOrder){return;}', $this->content());
    }

    public function testTheChartIsToldToFitItsNewSpot(): void
    {
        $this->assertStringContainsString('window.dispatchEvent(new Event("resize"))', $this->content());
    }
}
