<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignViewOrderSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignViewOrderSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignViewOrderSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignViewOrderSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testMovesTheStatisticsBlockAfterThePreview(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#preview-container").closest(".tab-content")', $js);
        $this->assertStringContainsString('mQuery(".stats-menu")', $js);
        $this->assertStringContainsString('mQuery(".stats-menu__content")', $js);
        $this->assertStringContainsString('$preview.after($menu,$stats)', $js);
    }

    public function testOnlyActsWhenAllThreePiecesAreOnThePage(): void
    {
        $this->assertStringContainsString('if(!$preview.length||!$menu.length||!$stats.length){return;}', $this->content());
    }

    public function testDoesNothingWhenAlreadyBelowThePreview(): void
    {
        $this->assertStringContainsString('compareDocumentPosition($preview[0])&Node.DOCUMENT_POSITION_PRECEDING', $this->content());
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;}', $js);
        $this->assertStringContainsString('mQuery(reorder);', $js);
    }

    public function testInstallsOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesCampaignViewOrder){return;}', $this->content());
    }

    public function testChartsAreToldToFitTheirNewSpot(): void
    {
        $this->assertStringContainsString('window.dispatchEvent(new Event("resize"))', $this->content());
    }
}
