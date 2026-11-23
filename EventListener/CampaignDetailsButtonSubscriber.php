<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\CampaignBundle\Entity\Campaign;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Details" (the campaign's page) to the three-dots menu of each row of the campaign list. The name of the campaign
 * now opens a modal (CampaignListClickSubscriber), so the page needs its own way in. Plain ajax navigation, no modal.
 */
class CampaignDetailsButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
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

        if (!$campaign instanceof Campaign
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_campaign_index' !== $event->getRoute()) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajax',
                    'href'        => $this->router->generate('mautic_campaign_action', ['objectAction' => 'view', 'objectId' => $campaign->getId()]),
                ],
                'btnText'   => $this->translator->trans('mautic.core.details'),
                'iconClass' => 'ri-information-line',
                'priority'  => 250, // first in the row menu, above Edit
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
