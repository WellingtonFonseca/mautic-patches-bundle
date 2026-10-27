<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Update" (add and remove the contacts of its segments now) to the three-dots menu of each
 * row of the campaign list and to the dropdown of the campaign detail page (the
 * one with Clone / Delete), next to the core's own options. The button POSTs to
 * CampaignRebuildController, which starts the update through CampaignRebuilder.
 * It is only offered when the user may edit the campaign and it is published. The row menu of the list also carries it,
 * hidden, for an inactive campaign: activating from that menu only swaps the status icon (no new menu is sent), so
 * the script (list-status-toggle.js, syncCampaigns) shows or hides it as the status changes.
 * From the detail page the controller returns to that page (?return=view).
 */
class CampaignListButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private CampaignRebuilder $rebuilder
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $campaign = $event->getItem();

        if (!$campaign instanceof Campaign) {
            return;
        }

        $location = $event->getLocation();
        $route    = $event->getRoute();
        $params   = ['id' => $campaign->getId()];
        $hidden   = false;

        if (ButtonHelper::LOCATION_LIST_ACTIONS === $location && 'mautic_campaign_index' === $route) {
            $priority = 150; // row menu of the campaign list, among Edit / Clone / Delete
            $hidden   = !$campaign->isPublished();
            $offered  = $this->rebuilder->hasEditAccess($campaign);
        } elseif (ButtonHelper::LOCATION_PAGE_ACTIONS === $location
            && 'mautic_campaign_action' === $route
            && 'view' === $event->getRequest()->attributes->get('objectAction')) {
            $priority = 150; // dropdown of the campaign detail page, next to Clone / Delete
            $params['return'] = 'view'; // come back to this page, not to the list
            $offered  = $this->rebuilder->canRebuild($campaign);
        } else {
            return;
        }

        if (!$offered) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajax',
                    'data-method' => 'POST',
                    'href'        => $this->router->generate('mautic_patches_campaign_rebuild', $params),
                ] + ($hidden ? ['hidden' => 'hidden'] : []),
                'btnText'   => $this->translator->trans('mautic.patches.campaign.rebuild'),
                'iconClass' => 'ri-refresh-line',
                'priority'  => $priority,
            ],
            $location
        );
    }
}
