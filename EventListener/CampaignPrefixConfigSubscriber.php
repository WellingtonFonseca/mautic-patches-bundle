<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the `campaign_prefixes` parameter in core's "Configurações de campanhas" form (alias `campaignconfig`), so the
 * Prefixos field (CampaignPrefixConfigExtension) is loaded with the saved list and saved with the rest.
 *
 * Runs after core's own listener (lower priority), because it changes the form that listener added.
 */
class CampaignPrefixConfigSubscriber implements EventSubscriberInterface
{
    private const CAMPAIGN_FORM = 'campaignconfig';
    private const THEME         = '@MauticPatches/Campaign/config_theme.html.twig';

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', -10],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $form = $event->getForms()[self::CAMPAIGN_FORM] ?? null;
        if (null === $form) {
            return;
        }

        $form['parameters'][CampaignPrefixes::PARAMETER] = [];
        // Core's theme of this tab lists its fields one by one: ours extends it and adds the Prefixos box.
        $form['formTheme'] = self::THEME;
        $event->addForm($form);
    }
}
