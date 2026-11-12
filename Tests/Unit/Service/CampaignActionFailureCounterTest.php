<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionContactSql;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionFailureCounter;
use PHPUnit\Framework\TestCase;

class CampaignActionFailureCounterTest extends TestCase
{
    protected function setUp(): void
    {
        // Mautic defines the prefix when it boots; the unit tests do not boot it.
        if (!defined('MAUTIC_TABLE_PREFIX')) {
            define('MAUTIC_TABLE_PREFIX', '');
        }
    }

    public function testNoStepsAsksNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeQuery');

        $this->assertSame([], (new CampaignActionFailureCounter($connection, new CampaignActionContactSql()))->countByEvent([]));
    }

    public function testCountsTheFailedOrRetriedLogsPerStepAsIntegers(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([['event_id' => '7', 'failed' => '4'], ['event_id' => '9', 'failed' => '1']]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeQuery')
            ->with(
                'SELECT l.event_id, COUNT(*) AS failed FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log l'
                    .' INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log f ON f.log_id = l.id'
                    .' WHERE l.event_id IN (:ids) AND (l.is_scheduled = 1 OR l.metadata LIKE \'%s:6:"failed";i:1;%\') GROUP BY l.event_id',
                ['ids' => [7, 9, 11]],
                ['ids' => Connection::PARAM_INT_ARRAY]
            )->willReturn($result);

        $this->assertSame([7 => 4, 9 => 1], (new CampaignActionFailureCounter($connection, new CampaignActionContactSql()))->countByEvent([7, 9, 11]));
    }
}
