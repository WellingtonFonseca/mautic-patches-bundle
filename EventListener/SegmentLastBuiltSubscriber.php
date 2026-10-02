<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\LocalizedDateFormatter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows when each segment was last rebuilt, under its name in the segment
 * list. The list had no such column (only the contact count), so after
 * "Update" nothing in the table said whether it had run. The core row template
 * calls customContent('segment.name', ...) for exactly this kind of addition;
 * the date comes from LeadList::getLastBuiltDate(), converted to the user's
 * time zone and written in the user's language (Mautic's own date helpers
 * would write the month in English, see LocalizedDateFormatter).
 */
class SegmentLastBuiltSubscriber implements EventSubscriberInterface
{
    public function __construct(private Translator $translator, private LocalizedDateFormatter $dateFormatter)
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
        $segment = $customContentEvent->getVars()['item'] ?? null;

        if ('segment.name' !== $customContentEvent->getContext() || !$segment instanceof LeadList) {
            return;
        }

        $builtDate = $segment->getLastBuiltDate();
        $builtText = null === $builtDate
            ? null
            : $this->dateFormatter->format((new DateTimeHelper($builtDate))->getLocalDateTime(), $this->translator->getLocale());

        $customContentEvent->addTemplate('@MauticPatches/Segment/last_built.html.twig', ['item' => $segment, 'builtText' => $builtText]);
    }
}
