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
 * The events of the campaign page with core's counts, for the "Etapas" tab (CampaignStepsTabSubscriber). Mautic 7 only:
 * the page of 7 no longer has `events` (its tabs come by ajax from CampaignController::eventStatsAction, whose count helpers are
 * private), so this repeats what that action does for an action or a condition step: logCountProcessed and logCountForPending, from the summary
 * table when campaign_use_summary is on, and in the page's date range when campaign_by_range is on (wiki/mautic7-port-plugins-n8n-patches.md).
 */
class CampaignSteps
{
    public function __construct(private EntityManagerInterface $entityManager, private CoreParametersHelper $parameters)
    {
    }

    /**
     * Every event of the page's campaign (triggers, actions and conditions), in core's order, deleted ones included as core's tab of 7 shows
     * them, without counts. The tab keeps the actions and the conditions and puts them in the order of the flow (CampaignFlowOrder).
     *
     * @param array<string, mixed> $pageVars the vars of core's details.html.twig
     *
     * @return list<array<string, mixed>>
     */
    public function steps(array $pageVars): array
    {
        $campaign = self::campaignOf($pageVars);
        if (null === $campaign) {
            return [];
        }

        $events = $this->entityManager->getRepository(Event::class)->getCampaignEvents($campaign->getId(), false);

        return array_values($events);
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed>       $pageVars
     *
     * @return list<array<string, mixed>> the steps with logCountProcessed and logCountForPending
     */
    public function withLogCounts(array $steps, array $pageVars): array
    {
        $campaign = self::campaignOf($pageVars);
        if (null === $campaign || [] === $steps) {
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
     * @param array<string, mixed> $pageVars
     */
    private static function campaignOf(array $pageVars): ?Campaign
    {
        $campaign = $pageVars['campaign'] ?? null;

        return $campaign instanceof Campaign && null !== $campaign->getId() ? $campaign : null;
    }

    /**
     * Core's range of eventStatsAction: none unless campaign_by_range, and the end day included. The page's dateFrom / dateTo are the
     * texts of its date range form ('Oct 1, 2026'), which core turns into Y-m-d for the stats url.
     *
     * @param array<string, mixed> $pageVars
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function range(array $pageVars): array
    {
        $from = $pageVars['dateFrom'] ?? null;
        $to   = $pageVars['dateTo'] ?? null;

        if (!$this->parameters->get('campaign_by_range') || empty($from) || empty($to)) {
            return [null, null];
        }

        return [self::day($from), self::day($to)->modify('+1 day')];
    }

    private static function day(mixed $date): \DateTimeImmutable
    {
        $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        return new \DateTimeImmutable((new \DateTimeImmutable($day))->format('Y-m-d'));
    }

    /**
     * A summary row per event is [no path, yes path, processed] (core's getCampaignLogCountsProcessed): the first two are all the logs.
     * Public for its test: SummaryRepository is final on 7, so the summary branch cannot be reached with a double.
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
