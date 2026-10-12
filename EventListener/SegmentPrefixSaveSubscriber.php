<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the prefix submitted with the segment form (or the API) once the segment is saved and has its id, and drops
 * it when the segment is deleted.
 */
class SegmentPrefixSaveSubscriber implements EventSubscriberInterface
{
    public function __construct(private SegmentPrefixStore $store)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_POST_SAVE   => 'onPostSave',
            LeadEvents::LIST_POST_DELETE => 'onPostDelete',
        ];
    }

    public function onPostSave(LeadListEvent $event): void
    {
        $segment = $event->getList();
        $prefix  = $this->store->pending($segment);

        if (null !== $prefix && null !== $segment->getId()) {
            $this->store->save((int) $segment->getId(), $prefix);
        }
    }

    public function onPostDelete(LeadListEvent $event): void
    {
        $segment = $event->getList();
        $id      = $segment->getId() ?? ($segment->deletedId ?? null);

        if (null !== $id) {
            $this->store->remove((int) $id);
        }
    }
}
