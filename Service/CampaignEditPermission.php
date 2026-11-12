<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;

/**
 * Whether the user can edit a campaign (own / other): the rule of the "Recalculate dates" box (shown) and of its apply route (allowed),
 * in one place so the two never disagree.
 */
class CampaignEditPermission
{
    public function __construct(private CorePermissions $security)
    {
    }

    public function allows(Campaign $campaign): bool
    {
        return $this->security->hasEntityAccess('campaign:campaigns:editown', 'campaign:campaigns:editother', $campaign->getCreatedBy());
    }
}
