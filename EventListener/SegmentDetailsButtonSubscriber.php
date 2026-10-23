<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Details" (the segment's page) to the three-dots menu of each row of the segment list. The name of the segment
 * now opens a modal (SegmentListClickSubscriber), so the page needs its own way in. Plain ajax navigation, no modal.
 */
class SegmentDetailsButtonSubscriber implements EventSubscriberInterface
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
        $segment = $event->getItem();

        if (!$segment instanceof LeadList
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_segment_index' !== $event->getRoute()) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajax',
                    'href'        => $this->router->generate('mautic_segment_action', ['objectAction' => 'view', 'objectId' => $segment->getId()]),
                ],
                'btnText'   => $this->translator->trans('mautic.core.details'),
                'iconClass' => 'ri-information-line',
                'priority'  => 250, // first in the row menu, above Edit
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
