<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\DTO\CampaignRebuildResult;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Target of the "Update" button of the campaign list and page (session login).
 * POST only. Starts the update through CampaignRebuilder, shows a flash message
 * and goes back to the list (or stays on the campaign's page with ?return=view).
 */
class CampaignRebuildController extends CommonController
{
    private const FLASH_BY_STATUS = [
        CampaignRebuildResult::DISPATCHED    => ['notice', 'mautic.patches.campaign.rebuild.started'],
        CampaignRebuildResult::RUNNING       => ['notice', 'mautic.patches.campaign.rebuild.running'],
        CampaignRebuildResult::NOT_FOUND     => ['error', 'mautic.patches.campaign.rebuild.not_found'],
        CampaignRebuildResult::NOT_PUBLISHED => ['error', 'mautic.patches.campaign.rebuild.not_published'],
        CampaignRebuildResult::FAILED        => ['error', 'mautic.patches.campaign.rebuild.failed'],
    ];

    /**
     * GET /s/campaign-rebuild/{id}/status: whether the update is still running, which the button's script
     * polls to know when it is done (see SegmentUpdateLockSubscriber::script).
     */
    public function statusAction(CampaignRebuilder $rebuilder, int $id): JsonResponse
    {
        $status = $rebuilder->status($id);

        if (null === $status) {
            return new JsonResponse(['error' => 'Campaign not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($status);
    }

    public function rebuildAction(Request $request, CampaignRebuilder $rebuilder, int $id): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->notFound();
        }

        $result = $rebuilder->request($id);

        if (CampaignRebuildResult::FORBIDDEN === $result->status) {
            return $this->accessDenied();
        }

        [$type, $message] = self::FLASH_BY_STATUS[$result->status];
        $page             = $request->getSession()->get('mautic.campaign.page', 1);
        $flashes          = [[
            'type'    => $type,
            'msg'     => $message,
            'msgVars' => ['%id%' => $id, '%name%' => $result->campaign?->getName() ?? '', '%error%' => $result->message],
        ]];

        if ('view' === $request->query->get('return') && null !== $result->campaign) {
            return $this->postActionRedirect([
                'returnUrl'       => $this->generateUrl('mautic_campaign_action', ['objectAction' => 'view', 'objectId' => $id]),
                'viewParameters'  => ['objectId' => $id],
                'contentTemplate' => 'Mautic\CampaignBundle\Controller\CampaignController::viewAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_campaign_index',
                    'mauticContent' => 'campaign',
                ],
                'flashes' => $flashes,
            ]);
        }

        return $this->postActionRedirect([
            'returnUrl'       => $this->generateUrl('mautic_campaign_index', ['page' => $page]),
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\CampaignBundle\Controller\CampaignController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_campaign_index',
                'mauticContent' => 'campaign',
            ],
            'flashes' => $flashes,
        ]);
    }
}
