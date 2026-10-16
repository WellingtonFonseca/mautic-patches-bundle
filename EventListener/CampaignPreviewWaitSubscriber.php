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

        $labels = [];
        foreach ($campaign->getEvents() as $event) {
            $label = $this->label($event);
            if ('' !== $label) {
                $labels['CampaignEvent_'.$event->getId()] = $label;
            }
        }

        $customContentEvent->addContent('<script>'.self::script($labels).'</script>');
    }

    /** The same text as core's builder: '' when the event runs at once. */
    public function label(Event $event): string
    {
        $inaction = Event::PATH_INACTION === $event->getDecisionPath() ? '_inaction' : '';

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
     * Leaves the texts for the page (CampaignBuilderWaitLabelSubscriber puts them on the nodes, now and after every
     * Mautic page load). Always sent, even empty: it replaces the texts of the campaign seen before over ajax.
     *
     * @param array<string, string> $labels node id => text
     */
    public static function script(array $labels): string
    {
        return 'window.mauticPatchesPreviewWaits='.json_encode((object) $labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).';'
            .'if(window.mauticPatchesApplyWaits){window.mauticPatchesApplyWaits();}';
    }
}
