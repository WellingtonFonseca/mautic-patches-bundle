<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Target of the "Update" button in the segment list (session login, unlike the
 * API). POST only. Starts the rebuild through SegmentRebuilder, shows a flash
 * message and goes back to the segment list.
 */
class SegmentRebuildController extends CommonController
{
    private const FLASH_BY_STATUS = [
        SegmentRebuildResult::DISPATCHED    => ['notice', 'mautic.patches.segment.rebuild.started'],
        SegmentRebuildResult::NOT_FOUND     => ['error', 'mautic.patches.segment.rebuild.not_found'],
        SegmentRebuildResult::NOT_PUBLISHED => ['error', 'mautic.patches.segment.rebuild.not_published'],
        SegmentRebuildResult::FAILED        => ['error', 'mautic.patches.segment.rebuild.failed'],
    ];

    /**
     * GET /s/segment-rebuild/{id}/status: the segment's last built date, which the
     * Update button's script polls to know when the background rebuild is done
     * (see SegmentUpdateLockSubscriber). Session login, like the click.
     */
    public function statusAction(SegmentRebuilder $rebuilder, int $id): JsonResponse
    {
        $status = $rebuilder->lastBuilt($id);

        if (null === $status) {
            return new JsonResponse(['error' => 'Segment not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($status);
    }

    public function rebuildAction(Request $request, SegmentRebuilder $rebuilder, int $id): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->notFound();
        }

        $result = $rebuilder->request($id);

        if (SegmentRebuildResult::FORBIDDEN === $result->status) {
            return $this->accessDenied();
        }

        [$type, $message] = self::FLASH_BY_STATUS[$result->status];
        $page             = $request->getSession()->get('mautic.segment.page', 1);
        $flashes          = [[
            'type'    => $type,
            'msg'     => $message,
            'msgVars' => ['%id%' => $id, '%name%' => $result->segment?->getName() ?? '', '%error%' => $result->message],
        ]];

        // Clicked on the segment's own page (?return=view): stay there. Otherwise back to the list.
        if ('view' === $request->query->get('return') && null !== $result->segment) {
            return $this->postActionRedirect([
                'returnUrl'       => $this->generateUrl('mautic_segment_action', ['objectAction' => 'view', 'objectId' => $id]),
                'viewParameters'  => ['objectId' => $id],
                'contentTemplate' => 'Mautic\LeadBundle\Controller\ListController::viewAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_segment_index',
                    'mauticContent' => 'list',
                ],
                'flashes' => $flashes,
            ]);
        }

        return $this->postActionRedirect([
            'returnUrl'       => $this->generateUrl('mautic_segment_index', ['page' => $page]),
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\LeadBundle\Controller\ListController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_segment_index',
                'mauticContent' => 'lead',
            ],
            'flashes' => $flashes,
        ]);
    }
}
