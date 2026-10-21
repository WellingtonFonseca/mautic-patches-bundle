<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A usability change, not a bug: the date / time fields of core (datetimepicker) accept anything and need the ':' and '-'
 * typed by hand (the hour of a campaign event, the dates of the segment filters...). The script formats them while the
 * user types, from the picker's own format: 1430 gives 14:30. See Resources/js/time-input-format.js for how.
 *
 * Injected the same way as SegmentFilterDuplicateSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class TimeInputFormatSubscriber implements EventSubscriberInterface
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
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/time-input-format.js')
            .';mauticPatchesTimeInputFormatApi.install(mQuery,document);</script>'
        );
    }
}
