<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Model;

use Mautic\CampaignBundle\Model\CampaignModel;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use MauticPlugin\MauticPatchesBundle\Service\SegmentSourceNames;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Core's campaign model, with the segments of the contact source named "PREFIX - Name". Core builds the select of the
 * source form, the node on the canvas and the list filter from getSourceLists(), so changing its answer changes all
 * of them. Config/services.php makes core's service id an instance of this class.
 */
class CampaignPrefixModel extends CampaignModel
{
    private SegmentPrefixStore $segmentPrefixes;

    // A setter, so the long constructor of core's model is not repeated here.
    #[Required]
    public function setSegmentPrefixes(SegmentPrefixStore $segmentPrefixes): void
    {
        $this->segmentPrefixes = $segmentPrefixes;
    }

    public function getSourceLists($sourceType = null, $globalOnly = false): array
    {
        return self::prefixSources($sourceType, parent::getSourceLists($sourceType, $globalOnly), $this->segmentPrefixes);
    }

    /**
     * @param array<int|string, mixed> $sources what getSourceLists answers: the names of one type, or all types by key
     *
     * @return array<int|string, mixed>
     */
    public static function prefixSources(?string $sourceType, array $sources, SegmentPrefixStore $store): array
    {
        if ('lists' === $sourceType) {
            return SegmentSourceNames::apply($sources, $store->all());
        }

        if (null === $sourceType && isset($sources['lists']) && is_array($sources['lists'])) {
            $sources['lists'] = SegmentSourceNames::apply($sources['lists'], $store->all());
        }

        return $sources;
    }
}
