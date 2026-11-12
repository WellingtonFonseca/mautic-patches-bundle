<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A second "Ações" tab on the campaign page, beside core's read-only one: each action step is the title of a collapse, which
 * will hold the contacts that failed on that step (wiki/patches-campaign-actions-tab.md). Core's page has the hooks 'tabs' and
 * 'tabs.content', so its template is not touched.
 */
class CampaignActionsTabSubscriber implements EventSubscriberInterface
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

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

        $customContentEvent->addTemplate('@MauticPatches/Campaign/actions_tab_pane.html.twig', ['actions' => $actions]);
    }
}
