<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

use Mautic\CampaignBundle\Entity\Campaign;

/**
 * Outcome of asking for a campaign update, so the button can answer with a flash message.
 */
final class CampaignRebuildResult
{
    public const DISPATCHED    = 'dispatched';
    public const NOT_FOUND     = 'not_found';
    public const FORBIDDEN     = 'forbidden';
    public const NOT_PUBLISHED = 'not_published';
    public const RUNNING       = 'running';
    public const FAILED        = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?Campaign $campaign = null,
        public readonly string $message = ''
    ) {
    }
}
