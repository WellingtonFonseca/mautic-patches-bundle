<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The schedule of a campaign event (hour, window of hours, days of the week) as lines under the wait text in the strip of its node,
 * in the builder and on the Preview tab, so nobody opens the node to know when it sends. This subscriber puts the formatter
 * (Resources/js/campaign-node-schedule.js) on the page with the words of the user's language; the strip itself belongs to
 * CampaignBuilderWaitLabelSubscriber, which asks the formatter for the lines. The Preview tab gets the fields of each event from
 * CampaignPreviewWaitSubscriber.
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

    public function __construct(private TranslatorInterface $translator)
    {
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
            .';window.mauticPatchesScheduleApi.configure('.json_encode($this->words(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).');</script>'
        );
    }

    /** @return array<string, string> */
    public function words(): array
    {
        $words = [];
        foreach (self::WORDS as $name) {
            $words[$name] = $this->translator->trans(self::PREFIX.$name);
        }

        return $words;
    }
}
