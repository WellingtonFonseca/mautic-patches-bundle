<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionFailureCounter;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionSteps;
use MauticPlugin\MauticPatchesBundle\Service\CampaignEditPermission;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A second "Ações" tab on the campaign page, beside core's read-only one: each action step is a row of badges (completed, failed, waiting); the failed and
 * waiting ones are buttons that load the contacts of that state in the table below; the waiting list of a Wait step also gets the
 * "Recalculate dates" box (CampaignWaitRecalculateScript) for who can edit the campaign (wiki/patches-campaign-actions-tab.md). Core's page has the hooks 'tabs' and
 * 'tabs.content', so its template is not touched.
 */
class CampaignActionsTabSubscriber implements EventSubscriberInterface
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    public function __construct(
        private CampaignActionSteps $steps,
        private CampaignActionFailureCounter $failureCounter,
        private CampaignEditPermission $editPermission
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

        $vars    = $customContentEvent->getVars();
        $actions = $this->steps->actionSteps($vars);

        // Core shows its own Ações tab only when the campaign has an action step: the same rule here.
        if ([] === $actions) {
            return;
        }

        if ($isLink) {
            $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_link.html.twig');

            return;
        }

        $actions = $this->steps->withLogCounts($actions, $vars);
        $failed  = $this->failureCounter->countByEvent(array_map(static fn (array $action): int => (int) $action['id'], $actions));
        $shares  = [];
        foreach ($actions as $action) {
            $shares[$action['id']] = $this->sharesOf($action, $failed[(int) $action['id']] ?? 0);
        }

        $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_pane.html.twig', [
            'actions'     => $actions,
            'shares'      => $shares,
            'recalculate' => $this->recalculableIds($actions, $vars['campaign'] ?? null),
        ]);
    }

    /**
     * The ids of the Wait steps whose dates the user may recalculate: the recalculation writes dates, so it needs the edit access of the
     * campaign (own / other), as its apply route checks again.
     *
     * @param list<array<string, mixed>> $actions
     *
     * @return list<int>
     */
    private function recalculableIds(array $actions, ?Campaign $campaign): array
    {
        if (null === $campaign || !$this->editPermission->allows($campaign)) {
            return [];
        }

        $waits = array_filter($actions, static fn (array $action): bool => RelativeWaitSubscriber::TYPE === ($action['type'] ?? null));

        return array_values(array_map(static fn (array $action): int => (int) $action['id'], $waits));
    }

    /**
     * The share of each state among the contacts that reached the step (completed + failed + waiting), so the three add up to about 100% (each is rounded to one decimal on its own). Core's
     * own yesPercent of an action is about the yes path, not the completed ones, and its pending count already leaves the failures out.
     * Every pending contact is waiting: core writes the same scheduled log whether the step holds it for a wait, a date or an hours / days window.
     *
     * @param array<string, mixed> $action a step of CampaignActionSteps (logCountProcessed, logCountForPending)
     *
     * @return array{completed: float, failed: float, waiting: float}
     */
    private function sharesOf(array $action, int $failed): array
    {
        $completed = (int) ($action['logCountProcessed'] ?? 0);
        $pending   = (int) ($action['logCountForPending'] ?? 0);
        $reached   = $completed + $failed + $pending;
        $share     = static fn (int $count): float => 0 === $reached ? 0.0 : round($count / $reached * 100, 1);

        return [
            'completed' => $share($completed),
            'failed'    => $share($failed),
            'waiting'   => $share($pending),
        ];
    }
}
