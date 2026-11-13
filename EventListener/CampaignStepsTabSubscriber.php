<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\CampaignStepFailureCounter;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEditPermission;
use MauticPlugin\MauticPatchesBundle\Service\CampaignFlowOrder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A second tab, "Etapas", on the campaign page, replacing core's read-only "Ações" and "Condições" ones (hidden by CSS): each action or condition step is a row, in the order of the flow, of badges (completed, failed, waiting); the failed and
 * waiting ones are buttons that load the contacts of that state in the table below; the waiting list of a Wait step also gets the
 * "Recalculate dates" box (CampaignWaitRecalculateScript) for who can edit the campaign (wiki/patches-campaign-steps-tab.md). Core's page has the hooks 'tabs' and
 * 'tabs.content', so its template is not touched.
 */
class CampaignStepsTabSubscriber implements EventSubscriberInterface
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    public function __construct(
        private CampaignStepFailureCounter $failureCounter,
        private CampaignEditPermission $editPermission,
        private CampaignFlowOrder $flowOrder
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'inject'];
    }

    public function inject(CustomContentEvent $customContentEvent): void
    {
        $isLink = $customContentEvent->checkContext(self::VIEW, 'tabs');
        $isPane = $customContentEvent->checkContext(self::VIEW, 'tabs.content');

        if (!$isLink && !$isPane) {
            return;
        }

        $steps = $this->stepsOf($customContentEvent->getVars()['events'] ?? []);

        // The tab lists the actions and the conditions: a campaign with neither has nothing to show.
        if ([] === $steps) {
            return;
        }

        if ($isLink) {
            $customContentEvent->addTemplate('@MauticPatches/Campaign/steps_tab_link.html.twig');

            return;
        }

        $failed = $this->failureCounter->countByEvent(array_map(static fn (array $step): int => (int) $step['id'], $steps));
        $shares = [];
        foreach ($steps as $step) {
            $shares[$step['id']] = $this->sharesOf($step, $failed[(int) $step['id']] ?? 0);
        }

        $customContentEvent->addTemplate('@MauticPatches/Campaign/steps_tab_pane.html.twig', [
            'steps'       => $steps,
            'shares'      => $shares,
            'recalculate' => $this->recalculableIds($steps, $customContentEvent->getVars()['campaign'] ?? null),
        ]);
    }

    /**
     * The actions and the conditions of the campaign in the order of its flow. The decisions (the triggers) only give the path: the whole tree is
     * sorted, then they are dropped.
     *
     * @param array<string, list<array<string, mixed>>> $eventsByType core's events of the page, grouped by type
     *
     * @return list<array<string, mixed>>
     */
    private function stepsOf(array $eventsByType): array
    {
        $all = array_merge(...array_values($eventsByType));

        return array_values(array_filter(
            $this->flowOrder->sort($all),
            static fn (array $event): bool => in_array($event['eventType'] ?? null, ['action', 'condition'], true)
        ));
    }

    /**
     * The ids of the Wait steps whose dates the user may recalculate: the recalculation writes dates, so it needs the edit access of the
     * campaign (own / other), as its apply route checks again.
     *
     * @param list<array<string, mixed>> $steps
     *
     * @return list<int>
     */
    private function recalculableIds(array $steps, ?Campaign $campaign): array
    {
        if (null === $campaign || !$this->editPermission->allows($campaign)) {
            return [];
        }

        $waits = array_filter($steps, static fn (array $step): bool => RelativeWaitSubscriber::TYPE === ($step['type'] ?? null));

        return array_values(array_map(static fn (array $step): int => (int) $step['id'], $waits));
    }

    /**
     * The share of each state among the contacts that reached the step (completed + failed + waiting), so the three add up to about 100% (each is rounded to one decimal on its own). Core's
     * own yesPercent of an action is about the yes path, not the completed ones, and its pending count already leaves the failures out.
     * Every pending contact is waiting: core writes the same scheduled log whether the step holds it for a wait, a date or an hours / days window.
     *
     * @param array<string, mixed> $step core's event of the page (logCountProcessed, logCountForPending)
     *
     * @return array{completed: float, failed: float, waiting: float}
     */
    private function sharesOf(array $step, int $failed): array
    {
        $completed = (int) ($step['logCountProcessed'] ?? 0);
        $pending   = (int) ($step['logCountForPending'] ?? 0);
        $reached   = $completed + $failed + $pending;
        $share     = static fn (int $count): float => 0 === $reached ? 0.0 : round($count / $reached * 100, 1);

        return [
            'completed' => $share($completed),
            'failed'    => $share($failed),
            'waiting'   => $share($pending),
        ];
    }
}
