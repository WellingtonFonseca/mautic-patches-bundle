<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignEventModalWidthSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignEventModalWidthSubscriberTest extends TestCase
{
    private function css(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignEventModalWidthSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignEventModalWidthSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeft(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);
    }

    public function testNothingIsInjectedOnOtherContexts(): void
    {
        $this->assertSame('', $this->css('page.header.right'));
    }

    public function testOnlyTheCampaignEventModalIsWidened(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(':root #CampaignEventModal .modal-dialog{', $css);
        $this->assertStringContainsString('width:1100px', $css, 'the size of the contact details modal');
        $this->assertStringContainsString('max-width:95vw', $css, 'never wider than the screen');
        $this->assertSame(1, substr_count($css, '{'), 'one rule, for that modal only');
    }
}
