<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Event\CampaignEvent;
use Mautic\CampaignBundle\CampaignEvents;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPrefixSaveSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use PHPUnit\Framework\TestCase;

class CampaignPrefixSaveSubscriberTest extends TestCase
{
    private function campaign(?int $id): Campaign
    {
        $campaign = new Campaign();
        if (null !== $id) {
            $property = new \ReflectionProperty(Campaign::class, 'id');
            $property->setValue($campaign, $id);
        }

        return $campaign;
    }

    public function testSubscribesToSaveAndDelete(): void
    {
        $events = CampaignPrefixSaveSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CampaignEvents::CAMPAIGN_POST_SAVE, $events);
        $this->assertArrayHasKey(CampaignEvents::CAMPAIGN_POST_DELETE, $events);
    }

    public function testSavesThePendingPrefixWithTheCampaignId(): void
    {
        $campaign = $this->campaign(7);
        $store   = $this->createMock(CampaignPrefixStore::class);
        $store->method('pending')->with($campaign)->willReturn('Compras');
        $store->expects($this->once())->method('save')->with(7, 'Compras');

        (new CampaignPrefixSaveSubscriber($store))->onPostSave(new CampaignEvent($campaign));
    }

    public function testACampaignSavedWithoutAPendingPrefixIsLeftAlone(): void
    {
        $store = $this->createMock(CampaignPrefixStore::class);
        $store->method('pending')->willReturn(null);
        $store->expects($this->never())->method('save');

        (new CampaignPrefixSaveSubscriber($store))->onPostSave(new CampaignEvent($this->campaign(7)));
    }

    public function testDeletingTheCampaignRemovesItsPrefix(): void
    {
        $store = $this->createMock(CampaignPrefixStore::class);
        $store->expects($this->once())->method('remove')->with(7);

        (new CampaignPrefixSaveSubscriber($store))->onPostDelete(new CampaignEvent($this->campaign(7)));
    }

    public function testDeletedIdIsUsedWhenTheIdWasCleared(): void
    {
        $campaign            = $this->campaign(null);
        $campaign->deletedId = 9;
        $store              = $this->createMock(CampaignPrefixStore::class);
        $store->expects($this->once())->method('remove')->with(9);

        (new CampaignPrefixSaveSubscriber($store))->onPostDelete(new CampaignEvent($campaign));
    }
}
