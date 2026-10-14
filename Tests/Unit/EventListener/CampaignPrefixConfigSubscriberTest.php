<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\CoreBundle\Helper\BundleHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPrefixConfigSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignPrefixConfigSubscriberTest extends TestCase
{
    public function testRunsAfterCoreListener(): void
    {
        $this->assertLessThan(0, CampaignPrefixConfigSubscriber::getSubscribedEvents()[ConfigEvents::CONFIG_ON_GENERATE][1]);
    }

    public function testAddsTheParameterToTheCampaignForm(): void
    {
        $event = new ConfigBuilderEvent($this->createMock(BundleHelper::class));
        $event->addForm(['formAlias' => 'campaignconfig', 'bundle' => 'CampaignBundle', 'parameters' => ['campaign_time_wait_on_event_false' => 30]]);

        (new CampaignPrefixConfigSubscriber())->onConfigGenerate($event);

        $this->assertSame(
            ['campaign_time_wait_on_event_false' => 30, 'campaign_prefixes' => []],
            $event->getForms()['campaignconfig']['parameters']
        );
        $this->assertSame('CampaignBundle', $event->getForms()['campaignconfig']['bundle']);
    }

    public function testDoesNothingWithoutTheCampaignForm(): void
    {
        $event = new ConfigBuilderEvent($this->createMock(BundleHelper::class));

        (new CampaignPrefixConfigSubscriber())->onConfigGenerate($event);

        $this->assertSame([], $event->getForms());
    }
}
