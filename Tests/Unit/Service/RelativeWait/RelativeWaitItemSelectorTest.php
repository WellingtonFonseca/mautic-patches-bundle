<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RelativeWait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Segment\SegmentItemResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Which items of a Custom Object the campaign's source segments select for a contact: the items the wait counts its date from.
 * The rules of the selection are the Custom Objects plugin's (SegmentItemResolver); this only finds the object of the field the
 * wait uses and asks it. Only a Custom Object field has items; a contact field never does.
 */
#[AllowMockObjectsWithoutExpectations]
class RelativeWaitItemSelectorTest extends TestCase
{
    private function contact(): Lead
    {
        $contact = new Lead();
        $contact->setId(7);

        return $contact;
    }

    private function connection(mixed $objectId): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($objectId);

        return $connection;
    }

    public function testAContactFieldHasNoItemsToSelect(): void
    {
        $resolver = $this->createMock(SegmentItemResolver::class);
        $resolver->expects($this->never())->method('resolveItemIds');
        $selector = new RelativeWaitItemSelector($this->connection(false), $resolver, $this->createMock(CustomObjectModel::class));

        $this->assertNull($selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'contact:attribution_date']));
    }

    public function testTheSegmentsOfTheCampaignSelectTheItemsOfTheObjectOfTheField(): void
    {
        $campaign = new Campaign();
        $object   = $this->createStub(CustomObject::class);
        $model    = $this->createMock(CustomObjectModel::class);
        $model->expects($this->once())->method('fetchEntity')->with(3)->willReturn($object);
        $resolver = $this->createMock(SegmentItemResolver::class);
        $resolver->expects($this->once())->method('resolveItemIds')->with($this->isInstanceOf(Lead::class), $campaign->getLists(), $object)->willReturn([4, 9]);
        $selector = new RelativeWaitItemSelector($this->connection('3'), $resolver, $model);

        $this->assertSame([4, 9], $selector->itemIds($this->contact(), $campaign, ['dateField' => 'custom_object:33']));
    }

    public function testNoSegmentConditionOnTheObjectLeavesTheChoiceOpen(): void
    {
        $model = $this->createStub(CustomObjectModel::class);
        $model->method('fetchEntity')->willReturn($this->createMock(CustomObject::class));
        $resolver = $this->createStub(SegmentItemResolver::class);
        $resolver->method('resolveItemIds')->willReturn(null);
        $selector = new RelativeWaitItemSelector($this->connection('3'), $resolver, $model);

        $this->assertNull($selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']), 'null: every item of the contact counts');
    }

    public function testAFieldThatIsNotThereOrAnObjectThatCannotBeLoadedLeavesTheChoiceOpen(): void
    {
        $resolver = $this->createMock(SegmentItemResolver::class);
        $resolver->expects($this->never())->method('resolveItemIds');

        $this->assertNull((new RelativeWaitItemSelector($this->connection(false), $resolver, $this->createMock(CustomObjectModel::class)))
            ->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:999']));

        $model = $this->createStub(CustomObjectModel::class);
        $model->method('fetchEntity')->willThrowException(new NotFoundException('gone'));
        $this->assertNull((new RelativeWaitItemSelector($this->connection('3'), $resolver, $model))
            ->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']));
    }

    public function testWithoutTheCustomObjectsPluginNothingIsSelected(): void
    {
        $selector = new RelativeWaitItemSelector($this->connection('3'), null, null);

        $this->assertNull($selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']));
    }

    public function testTheObjectOfTheFieldIsLoadedOnceForAWholeBatch(): void
    {
        $model = $this->createMock(CustomObjectModel::class);
        $model->expects($this->once())->method('fetchEntity')->with(3)->willReturn($this->createMock(CustomObject::class));
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn('3');
        $resolver = $this->createMock(SegmentItemResolver::class);
        $resolver->expects($this->exactly(3))->method('resolveItemIds')->willReturn([4]);
        $selector = new RelativeWaitItemSelector($connection, $resolver, $model);

        foreach ([1, 2, 3] as $i) {
            $this->assertSame([4], $selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']));
        }
    }

    public function testAnObjectThatCannotBeLoadedIsNotAskedForAgainInTheSameBatch(): void
    {
        $model = $this->createMock(CustomObjectModel::class);
        $model->expects($this->once())->method('fetchEntity')->willThrowException(new NotFoundException('gone'));
        $selector = new RelativeWaitItemSelector($this->connection('3'), $this->createMock(SegmentItemResolver::class), $model);

        $this->assertNull($selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']));
        $this->assertNull($selector->itemIds($this->contact(), new Campaign(), ['dateField' => 'custom_object:33']));
    }
}
