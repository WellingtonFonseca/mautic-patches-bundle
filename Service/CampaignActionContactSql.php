<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The SQL conditions (on campaign_leads, alias `entity`) that pick the contacts of one action step by state: the rule of each
 * number of the second "Ações" tab, in one place for its tables and the node modal's table. A contact is listed ONCE however many
 * logs (rotations) it has, so a list can be shorter than the number, which counts logs.
 */
class CampaignActionContactSql
{
    /** The metadata PendingEvent::fail() writes (serialized `failed => 1`). */
    public const FAILED_FLAG = '%s:6:"failed";i:1;%';

    /**
     * Scheduled and not failed: the contacts waiting for their time (a wait, or the hours/days restriction of the step).
     */
    public function waiting(int $eventId): string
    {
        return sprintf(
            'EXISTS (SELECT 1 FROM %1$scampaign_lead_event_log evlog WHERE evlog.lead_id = entity.lead_id AND evlog.event_id = %2$d AND evlog.is_scheduled = 1'
            .' AND NOT EXISTS (SELECT 1 FROM %1$scampaign_lead_event_failed_log failed WHERE failed.log_id = evlog.id))',
            MAUTIC_TABLE_PREFIX,
            $eventId
        );
    }

    /**
     * A failed log AND (the failure flag of the dispatch, or still scheduled: the retry core made). The failed log of a contact removed
     * from the campaign has neither, so it is left out.
     */
    public function failed(int $eventId): string
    {
        return sprintf(
            'EXISTS (SELECT 1 FROM %1$scampaign_lead_event_log evlog INNER JOIN %1$scampaign_lead_event_failed_log failed ON failed.log_id = evlog.id'
            ." WHERE evlog.lead_id = entity.lead_id AND evlog.event_id = %2\$d AND (evlog.is_scheduled = 1 OR evlog.metadata LIKE '%3\$s'))",
            MAUTIC_TABLE_PREFIX,
            $eventId,
            self::FAILED_FLAG
        );
    }
}
