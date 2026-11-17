<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\CampaignStepDispatchStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class CampaignStepDispatchStatusTest extends TestCase
{
    public function testOnlyTheDispatchStepsHaveAStatus(): void
    {
        $steps = [
            ['id' => 1, 'type' => 'email.send', 'properties' => ['status' => 'paused']],
            ['id' => 2, 'type' => 'campaign.wait', 'properties' => []],
            ['id' => 3, 'type' => 'n8ndispatch.hsm', 'properties' => ['status' => 'paused']],
            ['id' => 4, 'type' => 'n8ndispatch.sms', 'properties' => ['status' => 'production']],
        ];

        $this->assertSame([3 => 'paused', 4 => 'production'], (new CampaignStepDispatchStatus())->statusesOf($steps));
    }

    /**
     * @param array<string, mixed> $step
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('withoutAValidStatus')]
    public function testADispatchStepWithoutAValidStatusIsTest(array $step): void
    {
        $this->assertSame([5 => 'test'], (new CampaignStepDispatchStatus())->statusesOf([$step + ['id' => 5, 'type' => 'n8ndispatch.hsm']]));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function withoutAValidStatus(): array
    {
        return [
            'no properties'  => [[]],
            'no status'      => [['properties' => []]],
            'unknown status' => [['properties' => ['status' => 'archived']]],
            'not a string'   => [['properties' => ['status' => ['x']]]],
        ];
    }

    public function testNoStepsGivesNoStatuses(): void
    {
        $this->assertSame([], (new CampaignStepDispatchStatus())->statusesOf([]));
    }
}
