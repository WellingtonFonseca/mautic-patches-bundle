<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentListRepository;

/**
 * Gives the segment (LeadList) entity the repository that can sort by prefix. Tagged doctrine.event_listener
 * (loadClassMetadata) in Config/services.php.
 */
class SegmentRepositoryListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        if (LeadList::class === $metadata->getName()) {
            $metadata->setCustomRepositoryClass(SegmentListRepository::class);
        }
    }
}
