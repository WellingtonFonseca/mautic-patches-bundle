<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;

/**
 * The one place that decides whether a segment may be rebuilt now and starts
 * it. Used by the API (POST /api/segments/{id}/rebuild) and by the "Update"
 * button of the segment list, so both follow the same rules.
 */
class SegmentRebuilder
{
    public function __construct(
        private ListModel $listModel,
        private CorePermissions $security,
        private SegmentRebuildLauncher $launcher
    ) {
    }

    public function request(int $segmentId): SegmentRebuildResult
    {
        $segment = $this->listModel->getEntity($segmentId);

        if (null === $segment) {
            return new SegmentRebuildResult(SegmentRebuildResult::NOT_FOUND, null, "Segment {$segmentId} was not found.");
        }

        if (!$this->hasEditAccess($segment)) {
            return new SegmentRebuildResult(SegmentRebuildResult::FORBIDDEN, $segment, 'You do not have permission to edit this segment.');
        }

        if (!$segment->isPublished()) {
            return new SegmentRebuildResult(SegmentRebuildResult::NOT_PUBLISHED, $segment, "Segment {$segmentId} is not published, so it is not rebuilt.");
        }

        try {
            $this->launcher->launch($segmentId);
        } catch (\RuntimeException $e) {
            return new SegmentRebuildResult(SegmentRebuildResult::FAILED, $segment, $e->getMessage());
        }

        return new SegmentRebuildResult(SegmentRebuildResult::DISPATCHED, $segment);
    }

    /**
     * Whether request() could start this segment: edit access and published.
     */
    public function canRebuild(LeadList $segment): bool
    {
        return $segment->isPublished() && $this->hasEditAccess($segment);
    }

    private function hasEditAccess(LeadList $segment): bool
    {
        return $this->security->hasEntityAccess('lead:lists:editown', 'lead:lists:editother', $segment->getCreatedBy());
    }
}
