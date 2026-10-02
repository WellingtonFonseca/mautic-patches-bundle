<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller\Api;

use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/segments/{id}/rebuild: asks for a segment to be recalculated now
 * (what `mautic:segments:update --list-id={id}` does) instead of waiting for
 * the cron. Mautic's own API only has the command for that. The rebuild runs in
 * the background: the answer is 202 as soon as it was started, and progress is
 * the segment's "last built" date (GET /api/segments/{id}). The rules are in
 * SegmentRebuilder.
 *
 *   202 started          404 no such segment
 *   403 no edit access   409 segment is not published (the command skips those)
 *   500 could not start it
 */
class SegmentRebuildApiController extends AbstractController
{
    private const HTTP_CODE_BY_STATUS = [
        SegmentRebuildResult::NOT_FOUND     => Response::HTTP_NOT_FOUND,
        SegmentRebuildResult::FORBIDDEN     => Response::HTTP_FORBIDDEN,
        SegmentRebuildResult::NOT_PUBLISHED => Response::HTTP_CONFLICT,
        SegmentRebuildResult::FAILED        => Response::HTTP_INTERNAL_SERVER_ERROR,
    ];

    public function __construct(private SegmentRebuilder $rebuilder)
    {
    }

    public function rebuildAction(int $id): JsonResponse
    {
        $result = $this->rebuilder->request($id);

        if (SegmentRebuildResult::DISPATCHED !== $result->status) {
            $code = self::HTTP_CODE_BY_STATUS[$result->status];

            return new JsonResponse(['errors' => [['code' => $code, 'message' => $result->message]]], $code);
        }

        return new JsonResponse(
            ['segment' => ['id' => $result->segment->getId(), 'name' => $result->segment->getName()], 'status' => 'dispatched'],
            Response::HTTP_ACCEPTED
        );
    }
}
