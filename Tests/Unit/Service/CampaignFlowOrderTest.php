<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\CampaignFlowOrder;
use PHPUnit\Framework\TestCase;

class CampaignFlowOrderTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<int>
     */
    private function idsOf(array $events): array
    {
        return array_map('intval', array_column((new CampaignFlowOrder())->sort($events), 'id'));
    }

    public function testAChainFollowsItsParentsWhateverTheInputOrder(): void
    {
        $this->assertSame([1, 2, 3], $this->idsOf([
            ['id' => 3, 'parent_id' => 2],
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
        ]));
    }

    public function testTheYesPathComesBeforeTheNoPathAndEachPathIsWhole(): void
    {
        // 10 > condition 11 > yes: 12 > 14 / no: 13; the no path is listed after the whole yes path, not beside the condition
        $this->assertSame([10, 11, 12, 14, 13], $this->idsOf([
            ['id' => 13, 'parent_id' => 11, 'decisionPath' => 'no'],
            ['id' => 14, 'parent_id' => 12],
            ['id' => 12, 'parent_id' => 11, 'decisionPath' => 'yes'],
            ['id' => 11, 'parent_id' => 10],
            ['id' => 10, 'parent_id' => null],
        ]));
    }

    public function testSiblingsWithTheSamePathGoByIdAndTextIdsWork(): void
    {
        $this->assertSame([1, 2, 3], $this->idsOf([
            ['id' => '3', 'parent_id' => '1'],
            ['id' => '2', 'parent_id' => '1'],
            ['id' => '1', 'parent_id' => ''],
        ]));
    }

    public function testANodeWhoseParentIsMissingStartsAFlow(): void
    {
        $this->assertSame([4, 5], $this->idsOf([['id' => 5, 'parent_id' => 4], ['id' => 4, 'parent_id' => 99]]));
    }

    public function testTheFlowOrderWinsOverTheIdOrder(): void
    {
        // the child has a lower id than its parent (a step added later in the middle of the flow)
        $this->assertSame([5, 2, 3], $this->idsOf([
            ['id' => 2, 'parent_id' => 5],
            ['id' => 3, 'parent_id' => 2],
            ['id' => 5, 'parent_id' => null],
        ]));
    }

    public function testTheYesPathComesFirstEvenWhenTheNoPathHasTheLowerId(): void
    {
        $this->assertSame([10, 13, 12], $this->idsOf([
            ['id' => 12, 'parent_id' => 10, 'decisionPath' => 'no'],
            ['id' => 13, 'parent_id' => 10, 'decisionPath' => 'yes'],
            ['id' => 10, 'parent_id' => null],
        ]));
    }

    public function testNoEventsGiveNoEvents(): void
    {
        $this->assertSame([], $this->idsOf([]));
    }
}
