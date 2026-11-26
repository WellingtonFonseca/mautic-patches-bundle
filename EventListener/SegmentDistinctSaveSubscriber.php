<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctStore;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the DISTINCT setting submitted with the segment form once the segment has its id, starts a rebuild of a
 * published segment when the setting changed (the members change without a filter change), and drops the setting when
 * the segment is deleted.
 */
class SegmentDistinctSaveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SegmentDistinctStore $store,
        private SegmentRebuildLauncher $launcher,
        private LoggerInterface $logger
    ) {
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
        $pending = $this->store->pending($segment);
        $id      = $segment->getId();
        if (null === $pending || null === $id) {
            return;
        }

        $stored = $this->store->get((int) $id) ?? new SegmentDistinctConfig();
        if ($stored->equals($pending)) {
            return;
        }
        $this->store->save((int) $id, $pending);

        // Nothing to build when DISTINCT was never on; an unpublished segment is not built by the cron either.
        if (!$segment->isPublished() || (!$stored->enabled && !$pending->enabled)) {
            return;
        }
        try {
            $this->launcher->launch((int) $id);
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Segment DISTINCT: '.$exception->getMessage());
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
