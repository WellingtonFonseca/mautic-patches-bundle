<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuilder;
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

        return $this->postActionRedirect([
            'returnUrl'       => $this->generateUrl('mautic_segment_index', ['page' => $page]),
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\LeadBundle\Controller\ListController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_segment_index',
                'mauticContent' => 'lead',
            ],
            'flashes' => [[
                'type'    => $type,
                'msg'     => $message,
                'msgVars' => ['%id%' => $id, '%name%' => $result->segment?->getName() ?? '', '%error%' => $result->message],
            ]],
        ]);
    }
}
