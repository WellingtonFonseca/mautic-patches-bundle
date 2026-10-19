<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignUpdatingLineSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignUpdatingLineSubscriberTest extends TestCase
{
    public function testAddsTheLineUnderTheCampaignName(): void
    {
        $campaign = new Campaign();
        $event    = new CustomContentEvent('v', 'campaign.name', ['item' => $campaign]);

        (new CampaignUpdatingLineSubscriber())->injectViewCustomContent($event);

        $this->assertCount(1, $event->getTemplates());
        $this->assertSame('@MauticPatches/Campaign/updating_line.html.twig', $event->getTemplates()[0]['template']);
    }

    public function testNothingForOtherContextsOrItems(): void
    {
        $subscriber = new CampaignUpdatingLineSubscriber();

        $other = new CustomContentEvent('v', 'segment.name', ['item' => new Campaign()]);
        $subscriber->injectViewCustomContent($other);
        $notACampaign = new CustomContentEvent('v', 'campaign.name', ['item' => ['id' => 1]]);
        $subscriber->injectViewCustomContent($notACampaign);

        $this->assertSame([], $other->getTemplates());
        $this->assertSame([], $notACampaign->getTemplates());
    }
}
