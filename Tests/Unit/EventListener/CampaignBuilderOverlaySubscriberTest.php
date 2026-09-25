<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignBuilderOverlaySubscriber;
use PHPUnit\Framework\TestCase;

class CampaignBuilderOverlaySubscriberTest extends TestCase
{
    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT,
            CampaignBuilderOverlaySubscriber::getSubscribedEvents()
        );
    }

    public function testInjectsOverlayFixOnPageHeaderLeft(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new CampaignBuilderOverlaySubscriber())->injectViewCustomContent($event);

        $content = implode('', $event->getContent());
        $this->assertStringContainsString('#EventJumpOverlay', $content);
    }

    public function testDoesNothingOnOtherContexts(): void
    {
        $event = new CustomContentEvent('some_view', 'some.other.context');
        (new CampaignBuilderOverlaySubscriber())->injectViewCustomContent($event);

        $this->assertSame([], $event->getContent());
    }
}
