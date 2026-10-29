<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Twig\Helper\DateHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Preview tab of the campaign page never showed the wait of an event: only the builder's controller
 * (CampaignBundle EventController / CampaignController) builds the text ("esperar 2 dias"), the page's own data has the
 * trigger mode, interval and unit but no text. Core's templates are not touched: on 'tabs.content' of the campaign page
 * (the hook the page already has, inside the page content, so it also runs when Mautic loads the page over ajax) the
 * text is built here with the SAME translations and arguments as core and left in window.mauticPatchesPreviewWaits; the
 * script of CampaignBuilderWaitLabelSubscriber (window.mauticPatchesApplyWaits) puts it in the strip of each node, now
 * and after every Mautic page load (content that comes over ajax is not in the page yet when its inline script runs).
 */
class CampaignPreviewWaitSubscriber implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator, private DateHelper $dateHelper)
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
        if ('tabs.content' !== $customContentEvent->getContext()) {
            return;
        }

        $campaign = $customContentEvent->getVars()['campaign'] ?? null;
        if (!$campaign instanceof Campaign) {
            return;
        }

        $labels    = [];
        $schedules = [];
        foreach ($campaign->getEvents() as $event) {
            $label = $this->label($event);
            if ('' === $label) {
                continue;
            }

            $labels['CampaignEvent_'.$event->getId()] = $label;
            $schedule                                 = self::schedule($event);
            if (null !== $schedule) {
                $schedules['CampaignEvent_'.$event->getId()] = $schedule;
            }
        }

        $customContentEvent->addContent('<script>'.self::script($labels, $schedules).'</script>');
    }

    /**
     * Core's "_inaction" text ("entre 1 dia", "within") is a deadline for a DECISION: the no path only runs if the contact
     * did not act in time. A condition is evaluated once, at once, and its no path waits like any other event, so it
     * keeps the plain "esperar" text. The builder does the same in CampaignBuilderWaitLabelSubscriber.
     */
    public static function isInactionWait(Event $event): bool
    {
        return Event::PATH_INACTION === $event->getDecisionPath()
            && Event::TYPE_CONDITION !== $event->getParent()?->getEventType();
    }

    /** The same text as core's builder (except after a condition, see isInactionWait): '' when the event runs at once. */
    public function label(Event $event): string
    {
        $inaction = self::isInactionWait($event) ? '_inaction' : '';

        if (Event::TRIGGER_MODE_INTERVAL === $event->getTriggerMode()) {
            $interval = $event->getTriggerInterval();

            return $this->translator->trans(
                'mautic.campaign.connection.trigger.interval.label'.$inaction,
                [
                    '%number%' => $interval,
                    '%unit%'   => $this->translator->trans(
                        'mautic.campaign.event.intervalunit.'.$event->getTriggerIntervalUnit(),
                        ['%count%' => $interval]
                    ),
                ]
            );
        }

        if (Event::TRIGGER_MODE_DATE === $event->getTriggerMode() && null !== $event->getTriggerDate()) {
            $date = $event->getTriggerDate();

            return $this->translator->trans(
                'mautic.campaign.connection.trigger.date.label'.$inaction,
                [
                    '%full%' => $this->dateHelper->toFull($date),
                    '%time%' => $this->dateHelper->toTime($date),
                    '%date%' => $this->dateHelper->toShort($date),
                ]
            );
        }

        return '';
    }

    /**
     * The fields of an interval event that say WHEN it sends (hour, window of hours, days of the week), in the shape of the event
     * the builder holds (Mautic.campaignBuilderCanvasEvents), so the one formatter of the page (campaign-node-schedule.js) reads both.
     * null when the event has none of them: no extra lines.
     *
     * @return array<string, mixed>|null
     */
    public static function schedule(Event $event): ?array
    {
        if (Event::TRIGGER_MODE_INTERVAL !== $event->getTriggerMode()) {
            return null;
        }

        $hour  = $event->getTriggerHour();
        $start = $event->getTriggerRestrictedStartHour();
        $stop  = $event->getTriggerRestrictedStopHour();
        $days  = array_values($event->getTriggerRestrictedDaysOfWeek());
        if (null === $hour && null === $start && null === $stop && [] === $days) {
            return null;
        }

        return [
            'triggerMode'                 => Event::TRIGGER_MODE_INTERVAL,
            'triggerHour'                 => $hour?->format('H:i'),
            'triggerRestrictedStartHour'  => $start?->format('H:i'),
            'triggerRestrictedStopHour'   => $stop?->format('H:i'),
            'triggerRestrictedDaysOfWeek' => $days,
        ];
    }

    /**
     * Leaves the texts and the schedules for the page (CampaignBuilderWaitLabelSubscriber puts them on the nodes, now and after every
     * Mautic page load). Always sent, even empty: it replaces the texts of the campaign seen before over ajax.
     *
     * @param array<string, string>               $labels    node id => text
     * @param array<string, array<string, mixed>> $schedules node id => the fields of its schedule (self::schedule())
     */
    public static function script(array $labels, array $schedules = []): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;

        return 'window.mauticPatchesPreviewWaits='.json_encode((object) $labels, $flags).';'
            .'window.mauticPatchesPreviewSchedules='.json_encode((object) $schedules, $flags).';'
            .'if(window.mauticPatchesApplyWaits){window.mauticPatchesApplyWaits();}';
    }
}
