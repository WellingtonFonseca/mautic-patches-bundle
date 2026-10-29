<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The schedule of a campaign event (hour, window of hours, days of the week) as lines under the wait text in the strip of its node,
 * in the builder and on the Preview tab, so nobody opens the node to know when it sends. This subscriber puts the formatter
 * (Resources/js/campaign-node-schedule.js) on the page with the words of the user's language; the strip itself belongs to
 * CampaignBuilderWaitLabelSubscriber, which asks the formatter for the lines. The Preview tab gets the fields of each event from
 * CampaignPreviewWaitSubscriber.
 *
 * The same formatter writes the date of a "wait until a date" event in the language and the time zone of the user (core's text has
 * English month names and the system time zone): the user's own setting, else the system's, as core does at login
 * (CoreSubscriber sets _locale and _timezone that way).
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignNodeScheduleSubscriber implements EventSubscriberInterface
{
    private const PREFIX = 'mautic.patches.campaign.schedule.';

    /** The words the formatter uses, by the name it reads them with. */
    private const WORDS = [
        'hour', 'hour_minutes', 'at', 'window', 'range',
        'day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6',
    ];

    public function __construct(
        private TranslatorInterface $translator,
        private UserHelper $userHelper,
        private CoreParametersHelper $coreParametersHelper,
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

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-node-schedule.js')
            .';window.mauticPatchesScheduleApi.configure('
            .json_encode($this->words(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).','
            .json_encode($this->zone(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).');</script>'
        );
    }

    /** @return array<string, string> */
    public function words(): array
    {
        $words = [];
        foreach (self::WORDS as $name) {
            $words[$name] = $this->translator->trans(self::PREFIX.$name);
        }

        // core's own templates of the wait until a date: the formatter finds them in core's text and swaps the date
        $words['date_plain']    = $this->translator->trans('mautic.campaign.connection.trigger.date.label');
        $words['date_inaction'] = $this->translator->trans('mautic.campaign.connection.trigger.date.label_inaction');

        return $words;
    }

    /**
     * The language and the time zone of the user, as the browser's Intl wants them: "pt-BR" and "America/Sao_Paulo". The user's setting,
     * else the system's; a zone PHP does not know falls back to UTC.
     *
     * @return array{locale: string, timeZone: string}
     */
    public function zone(): array
    {
        $user     = $this->userHelper->getUser(true);
        $locale   = $user?->getLocale() ?: $this->coreParametersHelper->get('locale');
        $timeZone = $user?->getTimezone() ?: $this->coreParametersHelper->get('default_timezone');

        return [
            'locale'   => str_replace('_', '-', is_string($locale) && '' !== $locale ? $locale : 'en_US'),
            'timeZone' => is_string($timeZone) && in_array($timeZone, \DateTimeZone::listIdentifiers(), true) ? $timeZone : 'UTC',
        ];
    }
}
