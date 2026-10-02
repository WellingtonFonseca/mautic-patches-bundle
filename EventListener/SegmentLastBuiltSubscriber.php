<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows when each segment was last rebuilt, under its name in the segment
 * list. The list had no such column (only the contact count), so after
 * "Update" nothing in the table said whether it had run. The core row template
 * calls customContent('segment.name', ...) for exactly this kind of addition;
 * the date comes from LeadList::getLastBuiltDate() and is formatted by the
 * template with the user's own time zone and date format.
 */
class SegmentLastBuiltSubscriber implements EventSubscriberInterface
{
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

        $customContentEvent->addTemplate('@MauticPatches/Segment/last_built.html.twig', ['item' => $segment]);
    }
}
