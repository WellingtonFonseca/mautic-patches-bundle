<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\Summary;
use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * The action steps of the campaign page with core's counts, for the second "Ações" tab (CampaignActionsTabSubscriber). Mautic 7 only:
 * the page of 7 no longer has `events` (its tabs come by ajax from CampaignController::eventStatsAction, whose count helpers are
 * private), so this repeats what that action does for an action step: logCountProcessed and logCountForPending, from the summary
 * table when campaign_use_summary is on, and in the page's date range when campaign_by_range is on (wiki/mautic7-port-plugins-n8n-patches.md).
 */
class CampaignActionSteps
{
    public function __construct(private EntityManagerInterface $entityManager, private CoreParametersHelper $parameters)
    {
    }

    /**
     * The action steps of the page's campaign, in core's order, without counts (the tab link only needs to know there is one).
     *
     * @param array<string, mixed> $pageVars the vars of core's details.html.twig
     *
     * @return list<array<string, mixed>>
     */
    public function actionSteps(array $pageVars): array
    {
        $campaign = $pageVars['campaign'] ?? null;
        if (!$campaign instanceof Campaign || null === $campaign->getId()) {
            return [];
        }

        $events = $this->entityManager->getRepository(Event::class)->getCampaignEvents($campaign->getId());

        return array_values(array_filter($events, static fn (array $event): bool => 'action' === ($event['eventType'] ?? null)));
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed>       $pageVars
     *
     * @return list<array<string, mixed>> the steps with logCountProcessed and logCountForPending
     */
    public function withLogCounts(array $steps, array $pageVars): array
    {
        $campaign = $pageVars['campaign'] ?? null;
        if (!$campaign instanceof Campaign || [] === $steps) {
            return $steps;
        }

        [$from, $to]       = $this->range($pageVars);
        [$all, $processed] = $this->logCounts((int) $campaign->getId(), $from, $to);

        foreach ($steps as &$step) {
            $logged                     = array_sum($all[$step['id']] ?? []);
            $step['logCountProcessed']  = array_sum($processed[$step['id']] ?? []);
            $step['logCountForPending'] = $logged - $step['logCountProcessed'];
        }

        return $steps;
    }

    /**
     * Core's range of eventStatsAction: none unless campaign_by_range, and the end day included.
     *
     * @param array<string, mixed> $pageVars
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function range(array $pageVars): array
    {
        $from = $pageVars['dateFrom'] ?? null;
        $to   = $pageVars['dateTo'] ?? null;

        if (!$this->parameters->get('campaign_by_range') || !$from instanceof \DateTimeInterface || !$to instanceof \DateTimeInterface) {
            return [null, null];
        }

        return [
            new \DateTimeImmutable($from->format('Y-m-d')),
            (new \DateTimeImmutable($to->format('Y-m-d')))->modify('+1 day'),
        ];
    }

    /**
     * A summary row per event is [no path, yes path, processed] (core's getCampaignLogCountsProcessed): the first two are all the logs.
     *
     * @param array<int|string, array<int|string, int|string>> $summaryCounts
     *
     * @return array{0: array<int|string, array<int|string, int|string>>, 1: array<int|string, array<int|string, int|string>>}
     */
    public static function splitSummaryCounts(array $summaryCounts): array
    {
        $processed = [];
        foreach ($summaryCounts as $eventId => $counts) {
            $processed[$eventId] = [$counts[2] ?? 0];
            unset($summaryCounts[$eventId][2]);
        }

        return [$summaryCounts, $processed];
    }

    /**
     * @return array{0: array<int|string, array<int|string, int|string>>, 1: array<int|string, array<int|string, int|string>>} all logs and processed logs per event
     */
    private function logCounts(int $campaignId, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        if ($this->parameters->get('campaign_use_summary')) {
            return self::splitSummaryCounts($this->entityManager->getRepository(Summary::class)->getCampaignLogCounts($campaignId, $from, $to));
        }

        $logs = $this->entityManager->getRepository(LeadEventLog::class);
        $ttl  = (int) $this->parameters->get('campaign_event_cache_ttl');

        return [
            $logs->getCampaignLogCounts($campaignId, false, false, false, $from, $to, null, $ttl),
            $logs->getCampaignLogCounts($campaignId, true, false, false, $from, $to, null, $ttl),
        ];
    }
}
