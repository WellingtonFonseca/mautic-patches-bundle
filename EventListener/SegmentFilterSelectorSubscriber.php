<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * On the segment form, the Filters tab (`#filters`) shows the notice, then the selector that adds a filter
 * (`.form-group > .available-filters`), then the filters already added (`#leadlist_filters`). With many filters the
 * selector is far above the place where the next one is wanted: scroll up, add, scroll down. Here the filters come
 * first and the selector last, so a new filter appears right above it.
 *
 * Only the visual order changes (flex `order`), not the markup: core's script that adds a filter keeps working on the
 * same elements. The rules apply to the pane that holds `.available-filters` (`:has`, as the contact details modal
 * does), so another `#filters` somewhere else in the admin is left alone.
 *
 * Injected the same way as ModalFormButtonsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentFilterSelectorSubscriber implements EventSubscriberInterface
{
    private const PANE = '#filters:has(> .form-group > .available-filters)';

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $pane = ':root '.self::PANE;

        $customContentEvent->addContent(
            '<style>'
            .$pane.'.active{display:flex;flex-direction:column}'
            .$pane.' > .alert{order:0}'
            .$pane.' > #leadlist_filters{order:1}'
            .$pane.' > .form-group{order:2}'
            .'</style>'
        );
    }
}
