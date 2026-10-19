<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignUpdateLockSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentUpdateLockSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignUpdateLockSubscriberTest extends TestCase
{
    public function testAddsTheCampaignScriptAndTheStyleToThePageHeaderOnly(): void
    {
        $subscriber = new CampaignUpdateLockSubscriber();

        $other = new CustomContentEvent('v', 'content.above');
        $subscriber->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());

        $event = new CustomContentEvent('v', 'page.header.left');
        $subscriber->injectViewCustomContent($event);
        $this->assertCount(2, $event->getContent());
        $this->assertStringContainsString('/s/campaign-rebuild/', $event->getContent()[0]);
        $this->assertStringContainsString('.mautic-patches-updating', $event->getContent()[1]);
    }

    public function testTheCampaignScriptTargetsOnlyCampaignLinksAndLines(): void
    {
        $script = SegmentUpdateLockSubscriber::script(3000, 120000, 'campaign');

        $this->assertStringNotContainsString('segment', $script);
        $this->assertStringContainsString('window.mauticPatchesCampaignUpdateLock', $script);
        $this->assertStringContainsString('.campaign-updating-line', $script);
        $this->assertStringContainsString('r.running===false', $script);
        $this->assertStringNotContainsString('lastBuilt', $script);
    }

    public function testTheSegmentScriptIsNotChangedByTheCampaignOne(): void
    {
        $script = SegmentUpdateLockSubscriber::script(3000, 120000);

        $this->assertStringContainsString('/s/segment-rebuild/', $script);
        $this->assertStringContainsString('lastBuilt', $script);
        $this->assertStringNotContainsString('campaign', $script);
    }
}
