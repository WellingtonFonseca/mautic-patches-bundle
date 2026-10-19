<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticPatchesBundle\DTO\CampaignRebuildResult;

/**
 * The one place that decides whether a campaign may be updated now and starts
 * it. Used by the "Update" button of the campaign list and page.
 */
class CampaignRebuilder
{
    public function __construct(
        private CampaignModel $campaignModel,
        private CorePermissions $security,
        private CampaignRebuildLauncher $launcher
    ) {
    }

    public function request(int $campaignId): CampaignRebuildResult
    {
        $campaign = $this->campaignModel->getEntity($campaignId);

        if (null === $campaign) {
            return new CampaignRebuildResult(CampaignRebuildResult::NOT_FOUND, null, "Campaign {$campaignId} was not found.");
        }

        if (!$this->hasEditAccess($campaign)) {
            return new CampaignRebuildResult(CampaignRebuildResult::FORBIDDEN, $campaign, 'You do not have permission to edit this campaign.');
        }

        if (!$campaign->isPublished()) {
            return new CampaignRebuildResult(CampaignRebuildResult::NOT_PUBLISHED, $campaign, "Campaign {$campaignId} is not published, so it is not updated.");
        }

        if ($this->launcher->isRunning($campaignId)) {
            return new CampaignRebuildResult(CampaignRebuildResult::RUNNING, $campaign);
        }

        try {
            $this->launcher->launch($campaignId);
        } catch (\RuntimeException $e) {
            return new CampaignRebuildResult(CampaignRebuildResult::FAILED, $campaign, $e->getMessage());
        }

        return new CampaignRebuildResult(CampaignRebuildResult::DISPATCHED, $campaign);
    }

    /**
     * Whether the update of this campaign is still running, as the screen's "is it done?" check reads it.
     * Null when the campaign does not exist or the user may not update it (the same answer for both, so it
     * does not tell whether a campaign exists).
     *
     * @return array{running: bool}|null
     */
    public function status(int $campaignId): ?array
    {
        $campaign = $this->campaignModel->getEntity($campaignId);

        if (null === $campaign || !$this->hasEditAccess($campaign)) {
            return null;
        }

        return ['running' => $this->launcher->isRunning($campaignId)];
    }

    /**
     * Whether request() could start this campaign: edit access and published.
     */
    public function canRebuild(Campaign $campaign): bool
    {
        return $campaign->isPublished() && $this->hasEditAccess($campaign);
    }

    private function hasEditAccess(Campaign $campaign): bool
    {
        return $this->security->hasEntityAccess('campaign:campaigns:editown', 'campaign:campaigns:editother', $campaign->getCreatedBy());
    }
}
