<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListQueryBuilderGeneratedEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Core fires this event for the query of the new members (and their count) and of the orphans, so one predicate keeps
 * all three consistent (see SegmentDistinctQuery).
 */
class SegmentDistinctQuerySubscriber implements EventSubscriberInterface
{
    public function __construct(private SegmentDistinctQuery $query)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_FILTERS_QUERYBUILDER_GENERATED => 'onQueryBuilderGenerated',
        ];
    }

    public function onQueryBuilderGenerated(LeadListQueryBuilderGeneratedEvent $event): void
    {
        $this->query->apply($event->getSegment(), $event->getQueryBuilder());
    }
}
