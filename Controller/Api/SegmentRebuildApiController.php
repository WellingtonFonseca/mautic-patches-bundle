<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller\Api;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuildLauncher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/segments/{id}/rebuild: asks for a segment to be recalculated now
 * (what `mautic:segments:update --list-id={id}` does) instead of waiting for
 * the cron. Mautic's own API only has the command for that. The rebuild runs in
 * the background: the answer is 202 as soon as it was started, and progress is
 * the segment's "last built" date (GET /api/segments/{id}).
 *
 *   202 started          404 no such segment
 *   403 no edit access   409 segment is not published (the command skips those)
 *   500 could not start it
 */
class SegmentRebuildApiController extends AbstractController
{
    public function __construct(
        private ListModel $listModel,
        private CorePermissions $security,
        private SegmentRebuildLauncher $launcher
    ) {
    }

    public function rebuildAction(int $id): JsonResponse
    {
        $segment = $this->listModel->getEntity($id);

        if (null === $segment) {
            return $this->error(Response::HTTP_NOT_FOUND, "Segment {$id} was not found.");
        }

        if (!$this->security->hasEntityAccess('lead:lists:editown', 'lead:lists:editother', $segment->getCreatedBy())) {
            return $this->error(Response::HTTP_FORBIDDEN, 'You do not have permission to edit this segment.');
        }

        if (!$segment->isPublished()) {
            return $this->error(Response::HTTP_CONFLICT, "Segment {$id} is not published, so it is not rebuilt.");
        }

        try {
            $this->launcher->launch($id);
        } catch (\RuntimeException $e) {
            return $this->error(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }

        return new JsonResponse(
            ['segment' => ['id' => $segment->getId(), 'name' => $segment->getName()], 'status' => 'dispatched'],
            Response::HTTP_ACCEPTED
        );
    }

    private function error(int $code, string $message): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => $code, 'message' => $message]]], $code);
    }
}
