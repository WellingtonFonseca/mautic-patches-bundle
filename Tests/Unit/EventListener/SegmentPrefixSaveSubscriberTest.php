<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Event\LeadListEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixSaveSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;

class SegmentPrefixSaveSubscriberTest extends TestCase
{
    private function segment(?int $id): LeadList
    {
        $segment = new LeadList();
        if (null !== $id) {
            $property = new \ReflectionProperty(LeadList::class, 'id');
            $property->setValue($segment, $id);
        }

        return $segment;
    }

    public function testSubscribesToSaveAndDelete(): void
    {
        $events = SegmentPrefixSaveSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LeadEvents::LIST_POST_SAVE, $events);
        $this->assertArrayHasKey(LeadEvents::LIST_POST_DELETE, $events);
    }

    public function testSavesThePendingPrefixWithTheSegmentId(): void
    {
        $segment = $this->segment(7);
        $store   = $this->createMock(SegmentPrefixStore::class);
        $store->method('pending')->with($segment)->willReturn('Compras');
        $store->expects($this->once())->method('save')->with(7, 'Compras');

        (new SegmentPrefixSaveSubscriber($store))->onPostSave(new LeadListEvent($segment));
    }

    public function testASegmentSavedWithoutAPendingPrefixIsLeftAlone(): void
    {
        $store = $this->createMock(SegmentPrefixStore::class);
        $store->method('pending')->willReturn(null);
        $store->expects($this->never())->method('save');

        (new SegmentPrefixSaveSubscriber($store))->onPostSave(new LeadListEvent($this->segment(7)));
    }

    public function testDeletingTheSegmentRemovesItsPrefix(): void
    {
        $store = $this->createMock(SegmentPrefixStore::class);
        $store->expects($this->once())->method('remove')->with(7);

        (new SegmentPrefixSaveSubscriber($store))->onPostDelete(new LeadListEvent($this->segment(7)));
    }

    public function testDeletedIdIsUsedWhenTheIdWasCleared(): void
    {
        $segment            = $this->segment(null);
        $segment->deletedId = 9;
        $store              = $this->createMock(SegmentPrefixStore::class);
        $store->expects($this->once())->method('remove')->with(9);

        (new SegmentPrefixSaveSubscriber($store))->onPostDelete(new LeadListEvent($segment));
    }
}
