<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * The prefixes a campaign can have: the "Prefixos" box in Configurações (parameter
 * `campaign_prefixes`, one prefix per line).
 */
class CampaignPrefixes
{
    public const PARAMETER = 'campaign_prefixes';

    public function __construct(private CoreParametersHelper $coreParametersHelper)
    {
    }

    /**
     * @return string[] trimmed, no empty line, no repeated prefix, in the order they were typed
     */
    public function all(): array
    {
        $value = $this->coreParametersHelper->get(self::PARAMETER, []);

        return self::normalize(is_array($value) ? $value : []);
    }

    public function has(string $prefix): bool
    {
        return in_array($prefix, $this->all(), true);
    }

    /**
     * @param array<mixed> $lines
     *
     * @return string[]
     */
    public static function normalize(array $lines): array
    {
        return SegmentPrefixes::normalize($lines);
    }

    /**
     * The first problem with the lines typed in Configurações, or null (same rules as the segment prefixes).
     *
     * @param array<mixed> $lines
     */
    public static function problem(array $lines): ?string
    {
        return SegmentPrefixes::problem($lines);
    }
}
