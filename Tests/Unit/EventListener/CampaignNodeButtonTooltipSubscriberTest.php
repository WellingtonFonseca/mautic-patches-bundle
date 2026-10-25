<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignNodeButtonTooltipSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignNodeButtonTooltipSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignNodeButtonTooltipSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheScriptCarriesTheComponentAndInstallsItOnce(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesNodeButtonTooltipsApi', $content);
        $this->assertStringContainsString('.campaign-event-buttons [data-toggle="tooltip"]', $content);
        // the page header is rendered on every page load over ajax too: installed once, and marked as installed only after install() ran
        // (an install that throws is tried again on the next page load)
        $this->assertStringContainsString('if(!window.mauticPatchesNodeButtonTooltips){mauticPatchesNodeButtonTooltipsApi.install(window);window.mauticPatchesNodeButtonTooltips=true;}', $content);
    }

    public function testOnlyThePageHeaderGetsTheScript(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }

    public function testItListensToTheCustomContentOfTheViews(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignNodeButtonTooltipSubscriber::getSubscribedEvents());
    }
}
