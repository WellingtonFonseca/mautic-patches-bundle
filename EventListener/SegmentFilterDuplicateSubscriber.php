<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * "Duplicar condição" on every filter of the segment form: a copy of that filter (same field, operator, value and AND / OR)
 * right below it, to write the same condition for another value without searching the field again. Core's copy button
 * copies a whole OR group to the end of the list and is hidden on an AND filter. The behaviour is in
 * Resources/js/segment-filter-duplicate.js; the script only acts where the filters list (#leadlist_filters) exists.
 *
 * Injected the same way as SegmentInChipsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentFilterDuplicateSubscriber implements EventSubscriberInterface
{
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

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-filter-duplicate.js')
            .';mauticPatchesFilterDuplicateApi.install(mQuery,Mautic);</script>'
        );
    }
}
