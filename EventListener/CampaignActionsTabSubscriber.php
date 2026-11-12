<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionFailureCounter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A second "Ações" tab on the campaign page, beside core's read-only one: each action step is a row of badges (completed, failed, scheduled, waiting); the failed and
 * waiting ones are buttons that load the contacts of that state in the table below; the waiting list of a Wait step also gets the
 * "Recalculate dates" box (CampaignWaitRecalculateScriptSubscriber) for who can edit the campaign (wiki/patches-campaign-actions-tab.md). Core's page has the hooks 'tabs' and
 * 'tabs.content', so its template is not touched.
 */
class CampaignActionsTabSubscriber implements EventSubscriberInterface
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    public function __construct(
        private CampaignActionFailureCounter $failureCounter,
        private CorePermissions $security
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

        $actions = $customContentEvent->getVars()['events']['action'] ?? [];

        // Core shows its own Ações tab only when the campaign has an action step: the same rule here.
        if ([] === $actions) {
            return;
        }

        if ($isLink) {
            $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_link.html.twig');

            return;
        }

        $failed = $this->failureCounter->countByEvent(array_map(static fn (array $action): int => (int) $action['id'], $actions));
        $shares = [];
        foreach ($actions as $action) {
            $shares[$action['id']] = $this->sharesOf($action, $failed[(int) $action['id']] ?? 0);
        }

        $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_pane.html.twig', [
            'actions'     => $actions,
            'shares'      => $shares,
            'recalculate' => $this->recalculableIds($actions, $customContentEvent->getVars()['campaign'] ?? null),
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
        if (null === $campaign || !$this->security->hasEntityAccess('campaign:campaigns:editown', 'campaign:campaigns:editother', $campaign->getCreatedBy())) {
            return [];
        }

        $waits = array_filter($actions, static fn (array $action): bool => RelativeWaitSubscriber::TYPE === ($action['type'] ?? null));

        return array_values(array_map(static fn (array $action): int => (int) $action['id'], $waits));
    }

    /**
     * The share of each state among the contacts that reached the step (completed + failed + waiting), so the four add up to 100%. Core's
     * own yesPercent of an action is about the yes path, not the completed ones, and its pending count already leaves the failures out.
     * The pending contacts of a step with an hours / days restriction are held for the window (scheduled); the others wait for a wait
     * or a date. Core writes the same log for both, so only the step's own settings tell them apart.
     *
     * @param array<string, mixed> $action core's event of the page (logCountProcessed, logCountForPending, the trigger settings)
     *
     * @return array{completed: float, failed: float, scheduled: float, waiting: float}
     */
    private function sharesOf(array $action, int $failed): array
    {
        $completed = (int) ($action['logCountProcessed'] ?? 0);
        $pending   = (int) ($action['logCountForPending'] ?? 0);
        $reached   = $completed + $failed + $pending;
        $share     = static fn (int $count): float => 0 === $reached ? 0.0 : round($count / $reached * 100, 1);
        $held      = $this->isRestricted($action);

        return [
            'completed' => $share($completed),
            'failed'    => $share($failed),
            'scheduled' => $held ? $share($pending) : 0.0,
            'waiting'   => $held ? 0.0 : $share($pending),
        ];
    }

    /**
     * @param array<string, mixed> $action
     */
    private function isRestricted(array $action): bool
    {
        foreach (['triggerHour', 'triggerRestrictedStartHour', 'triggerRestrictedStopHour', 'triggerRestrictedDaysOfWeek'] as $key) {
            if (!empty($action[$key])) {
                return true;
            }
        }

        return false;
    }
}
