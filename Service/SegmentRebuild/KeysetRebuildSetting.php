<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticPatchesBundle\Integration\MauticPatchesIntegration;

/**
 * The switch "Segmentos por id" of the plugin settings modal (Integration\MauticPatchesIntegration::appendToForm, Features tab). Not saved yet = on.
 */
class KeysetRebuildSetting
{
    public const FIELD = 'keyset_rebuild';

    /**
     * @param \Closure(): IntegrationHelper $integrationHelper lazy: the helper reaches the segment service, so it cannot be built with it
     */
    public function __construct(private \Closure $integrationHelper)
    {
    }

    public function isEnabled(): bool
    {
        $integration = ($this->integrationHelper)()->getIntegrationObject(MauticPatchesIntegration::NAME);
        if (false === $integration) {
            return true;
        }

        return (bool) ($integration->getIntegrationSettings()->getFeatureSettings()[self::FIELD] ?? true);
    }
}
