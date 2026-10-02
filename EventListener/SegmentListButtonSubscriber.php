<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Update" (recalculate the segment now) to the three-dots menu of each
 * row of the segment list and to the dropdown of the segment detail page (the
 * one with Clone / Delete), next to the core's own options. The button POSTs to
 * SegmentRebuildController, which uses the same SegmentRebuilder as the API.
 * It is only offered when the user may edit the segment and it is published.
 * From the detail page the controller returns to that page (?return=view).
 */
class SegmentListButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private SegmentRebuilder $rebuilder
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

        if (!$segment instanceof LeadList || !$this->rebuilder->canRebuild($segment)) {
            return;
        }

        $location = $event->getLocation();
        $route    = $event->getRoute();
        $params   = ['id' => $segment->getId()];

        if (ButtonHelper::LOCATION_LIST_ACTIONS === $location && 'mautic_segment_index' === $route) {
            $priority = 150; // row menu of the segment list, among Edit / Clone / Delete
        } elseif (ButtonHelper::LOCATION_PAGE_ACTIONS === $location
            && 'mautic_segment_action' === $route
            && 'view' === $event->getRequest()->attributes->get('objectAction')) {
            $priority = 150; // dropdown of the segment detail page, next to Clone / Delete
            $params['return'] = 'view'; // come back to this page, not to the list
        } else {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajax',
                    'data-method' => 'POST',
                    'href'        => $this->router->generate('mautic_patches_segment_rebuild', $params),
                ],
                'btnText'   => $this->translator->trans('mautic.patches.segment.rebuild'),
                'iconClass' => 'ri-refresh-line',
                'priority'  => $priority,
            ],
            $location
        );
    }
}
