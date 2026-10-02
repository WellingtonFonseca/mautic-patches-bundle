<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

use Mautic\LeadBundle\Entity\LeadList;

/**
 * Outcome of asking for a segment rebuild, so the API and the segment list
 * button can each answer in their own way (HTTP code or flash message).
 */
final class SegmentRebuildResult
{
    public const DISPATCHED    = 'dispatched';
    public const NOT_FOUND     = 'not_found';
    public const FORBIDDEN     = 'forbidden';
    public const NOT_PUBLISHED = 'not_published';
    public const FAILED        = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?LeadList $segment = null,
        public readonly string $message = ''
    ) {
    }
}
