<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The plugin tiles of Settings > Plugins keep the color they had when the page opened: after the "Ativo" switch of a plugin is saved, its
 * tile stays gray (turned on) or colored (turned off) until the page is reloaded. Core's answer is right, but it is not read: see
 * Resources/js/plugin-tile-status.js, which wraps core's Mautic.integrationConfigOnLoad so the answer paints the tile.
 *
 * Injected the same way as FlashesLongerSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class PluginTileStatusSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/plugin-tile-status.js')
            .';mauticPatchesPluginTileStatusApi.install(mQuery,Mautic);</script>'
        );
    }
}
