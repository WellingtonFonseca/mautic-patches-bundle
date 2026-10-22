<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Segment\RelativeDate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A usability change, not a bug: the date / time fields of core (datetimepicker) accept anything and need the ':' and '-'
 * typed by hand (the hour of a campaign event, the dates of the segment filters...). The script formats them while the
 * user types, from the picker's own format: 1430 gives 14:30. See Resources/js/time-input-format.js for how.
 *
 * The date filters of the segment form also take relative values, and core keeps a filter relative only when it is one of its
 * keywords translated into the language of the user (hoje, esta semana...) or starts with a sign: the page is given that list
 * (RelativeDate, core's own service) as window.mauticPatchesRelativeDates before the formatter starts, as a map of every word accepted
 * (the language of the user, and the same keywords in English: the official documentation is in English) to the word core keeps
 * relative, which the field takes when it loses focus.
 *
 * Injected the same way as SegmentFilterDuplicateSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class TimeInputFormatSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RelativeDate $relativeDate,
        private TranslatorInterface $translator
    ) {
    }

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

        // every word accepted (lower case) to the word core keeps relative: the language of the user first, then the same keyword in English
        // (not when the translation is missing, which gives the key back, and never over a word of the user's language)
        $strings = $this->relativeDate->getRelativeDateStrings();
        $words   = [];
        foreach ($strings as $local) {
            $words[mb_strtolower($local)] = $local;
        }
        foreach ($strings as $key => $local) {
            $english = $this->translator->trans($key, [], null, 'en_US');
            if ($english !== $key) {
                $words += [mb_strtolower($english) => $local];
            }
        }
        $keywords = json_encode($words, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);

        $customContentEvent->addContent(
            '<script>window.mauticPatchesRelativeDates='.$keywords.';'.file_get_contents(__DIR__.'/../Resources/js/time-input-format.js')
            .';mauticPatchesTimeInputFormatApi.install(mQuery,document);</script>'
        );
    }
}
