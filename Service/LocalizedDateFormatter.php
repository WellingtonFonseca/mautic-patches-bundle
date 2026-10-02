<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * Writes a date and time in the user's language ("2 de outubro de 2026 02:35").
 *
 * Mautic's own date helpers (dateToFull and friends) call PHP's
 * DateTime::format() with the configured pattern, e.g. 'F j, Y g:i a T', and
 * that always writes the month in English, whatever the user's language.
 * IntlDateFormatter knows the locale. The clock shown is the one of the given
 * date's own time zone, so pass it already converted to the user's zone.
 */
class LocalizedDateFormatter
{
    public function format(\DateTimeInterface $date, string $locale): string
    {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::SHORT,
            $date->getTimezone()
        );

        return (string) $formatter->format($date);
    }
}
