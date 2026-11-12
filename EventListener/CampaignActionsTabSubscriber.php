<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\CampaignActionFailureCounter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A second "Ações" tab on the campaign page, beside core's read-only one: each action step is the title of a collapse, which
 * will hold the contacts that failed on that step (wiki/patches-campaign-actions-tab.md). Core's page has the hooks 'tabs' and
 * 'tabs.content', so its template is not touched.
 */
class CampaignActionsTabSubscriber implements EventSubscriberInterface
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    public function __construct(private CampaignActionFailureCounter $failureCounter)
    {
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

        $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_pane.html.twig', ['actions' => $actions, 'shares' => $shares]);
    }

    /**
     * The share of each state among the contacts that reached the step (completed + failed + waiting), so the three add up to 100%. Core's
     * own yesPercent of an action is about the yes path, not the completed ones, and its pending count already leaves the failures out.
     *
     * @param array<string, mixed> $action core's event of the page (logCountProcessed, logCountForPending)
     *
     * @return array{completed: float, failed: float, waiting: float}
     */
    private function sharesOf(array $action, int $failed): array
    {
        $completed = (int) ($action['logCountProcessed'] ?? 0);
        $waiting   = (int) ($action['logCountForPending'] ?? 0);
        $reached   = $completed + $failed + $waiting;

        if (0 === $reached) {
            return ['completed' => 0.0, 'failed' => 0.0, 'waiting' => 0.0];
        }

        return [
            'completed' => round($completed / $reached * 100, 1),
            'failed'    => round($failed / $reached * 100, 1),
            'waiting'   => round($waiting / $reached * 100, 1),
        ];
    }
}
