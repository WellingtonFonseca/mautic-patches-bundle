<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The change log of the plugin (CHANGELOG.md) is drawn by Integration\MauticPatchesIntegration on the first tab of its settings modal; core lets a
 * plugin add no tab, so Resources/js/plugin-changelog-tab.js moves it into one of its own. The few rules for its look are injected with it.
 *
 * Injected the same way as FlashesLongerSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class PluginChangelogTabSubscriber implements EventSubscriberInterface
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
            '<style>.patches-changelog{max-height:60vh;overflow-y:auto}.patches-changelog__day{margin-bottom:12px}'
            .'.patches-changelog__day ul{margin:4px 0 0;padding-left:20px}</style>'
            .'<script>'.file_get_contents(__DIR__.'/../Resources/js/plugin-changelog-tab.js')
            .';mauticPatchesChangelogTabApi.install(mQuery,Mautic);</script>'
        );
    }
}
