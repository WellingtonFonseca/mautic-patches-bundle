<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\CampaignActionContactSql;
use PHPUnit\Framework\TestCase;

class CampaignActionContactSqlTest extends TestCase
{
    protected function setUp(): void
    {
        // Mautic defines the prefix when it boots; the unit tests do not boot it.
        if (!defined('MAUTIC_TABLE_PREFIX')) {
            define('MAUTIC_TABLE_PREFIX', '');
        }
    }

    public function testWaitingIsScheduledWithoutAFailedLog(): void
    {
        $this->assertSame(
            'EXISTS (SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log evlog WHERE evlog.lead_id = entity.lead_id AND evlog.event_id = 7 AND evlog.is_scheduled = 1'
            .' AND NOT EXISTS (SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log failed WHERE failed.log_id = evlog.id))',
            (new CampaignActionContactSql())->waiting(7)
        );
    }

    public function testFailedIsAFailedLogWithTheFlagOrStillScheduled(): void
    {
        $this->assertSame(
            'EXISTS (SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log evlog INNER JOIN '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_failed_log failed ON failed.log_id = evlog.id'
            .' WHERE evlog.lead_id = entity.lead_id AND evlog.event_id = 7 AND (evlog.is_scheduled = 1 OR evlog.metadata LIKE \'%s:6:"failed";i:1;%\'))',
            (new CampaignActionContactSql())->failed(7)
        );
    }
}
