<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignNodeStatStyleSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignNodeStatStyleSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('', $context);
        (new CampaignNodeStatStyleSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame('injectViewCustomContent', CampaignNodeStatStyleSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_CONTENT]);
    }

    public function testTheCountsOfEveryNodeAreSetApartAtTheRightOnPageHeaderLeftOnly(): void
    {
        // core's `pa-4` on the counts is !important: the padding must be too
        $this->assertSame('<style>.campaign-event-stat{margin-left:auto;padding-left:24px!important}</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testNoCountLooksClickable(): void
    {
        // the counts open nothing: the contacts of a step are on the "Etapas" tab
        $this->assertStringNotContainsString('cursor', $this->content());
        $this->assertStringNotContainsString('<script', $this->content());
    }
}
