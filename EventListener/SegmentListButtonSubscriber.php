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
 * row of the segment list, next to Edit / Clone / Delete. The button POSTs to
 * SegmentRebuildController, which uses the same SegmentRebuilder as the API.
 * It is only offered when the user may edit the segment and it is published.
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

        if (ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_segment_index' !== $event->getRoute()
            || !$segment instanceof LeadList
            || !$this->rebuilder->canRebuild($segment)) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajax',
                    'data-method' => 'POST',
                    'href'        => $this->router->generate('mautic_patches_segment_rebuild', ['id' => $segment->getId()]),
                ],
                'btnText'   => $this->translator->trans('mautic.patches.segment.rebuild'),
                'iconClass' => 'ri-refresh-line',
                'priority'  => 150,
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
