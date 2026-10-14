<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixRepository;

/**
 * Gives the campaign (Campaign) entity the repository that can sort by prefix. Tagged doctrine.event_listener
 * (loadClassMetadata) in Config/services.php.
 */
class CampaignRepositoryListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        if (Campaign::class === $metadata->getName()) {
            $metadata->setCustomRepositoryClass(CampaignPrefixRepository::class);
        }
    }
}
