<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the `segment_prefixes` parameter in core's "Configurações de segmentos" form (alias `segment_config`), so the
 * Prefixos field (SegmentPrefixConfigExtension) is loaded with the saved list and saved with the rest.
 *
 * Runs after core's own listener (lower priority), because it changes the form that listener added.
 */
class SegmentPrefixConfigSubscriber implements EventSubscriberInterface
{
    private const SEGMENT_FORM = 'segment_config';

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', -10],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $form = $event->getForms()[self::SEGMENT_FORM] ?? null;
        if (null === $form) {
            return;
        }

        $form['parameters'][SegmentPrefixes::PARAMETER] = [];
        $event->addForm($form);
    }
}
