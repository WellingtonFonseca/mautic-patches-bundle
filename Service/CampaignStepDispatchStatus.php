<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The status (test, production, paused) of the n8n dispatch steps of a campaign, for the badge of the "Etapas" tab. Only DATA of the other
 * plugin is read: the step type starts with "n8ndispatch." and the status is in the step properties. No class of that plugin is used, so this
 * plugin boots without it. A dispatch step with no status is "test", the same default the n8n plugin applies in its own preview.
 */
class CampaignStepDispatchStatus
{
    private const TYPE_PREFIX    = 'n8ndispatch.';
    private const DEFAULT_STATUS = 'test';
    private const STATUSES       = ['test', 'production', 'paused'];

    /**
     * @param list<array<string, mixed>> $steps core's events of the page (id, type, properties)
     *
     * @return array<int, string> step id => status, only for the n8n dispatch steps
     */
    public function statusesOf(array $steps): array
    {
        $statuses = [];
        foreach ($steps as $step) {
            if (!str_starts_with((string) ($step['type'] ?? ''), self::TYPE_PREFIX)) {
                continue;
            }

            $status                      = $step['properties']['status'] ?? null;
            $statuses[(int) $step['id']] = in_array($status, self::STATUSES, true) ? $status : self::DEFAULT_STATUS;
        }

        return $statuses;
    }
}
