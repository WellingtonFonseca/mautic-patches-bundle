<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The "Sobre" block and the change log of the plugin (CHANGELOG.md) are drawn by Integration\MauticPatchesIntegration on the first tab of its
 * settings modal; core lets a plugin add no tab, so the two scripts of Resources/js move them into tabs of their own. The few rules for their
 * look are injected with the scripts, as classes of core and layout only, so both themes work.
 *
 * Injected the same way as FlashesLongerSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class PluginSettingsTabsSubscriber implements EventSubscriberInterface
{
    private const SCRIPTS = [
        'plugin-about-tab.js'     => 'mauticPatchesAboutTabApi',
        'plugin-changelog-tab.js' => 'mauticPatchesChangelogTabApi',
    ];

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

        $scripts = '';
        foreach (self::SCRIPTS as $file => $api) {
            $script = file_get_contents(__DIR__.'/../Resources/js/'.$file);
            if (false === $script) {
                return;
            }
            $scripts .= $script.';'.$api.'.install(mQuery,Mautic);';
        }

        $customContentEvent->addContent(
            // the plugin's settings modal is core's 600px: widened to 780px, and only the modal that holds the About block
            '<style>.modal-dialog:has(.patches-about-block){width:780px;max-width:95vw}'
            .'.patches-about{padding:24px 16px}.patches-about__icon{height:160px;margin:0 auto 16px}.patches-about__title{margin:0 0 8px}.patches-about__text{max-width:520px;margin:0 auto}'
            .'.patches-changelog{max-height:60vh;overflow-y:auto}.patches-changelog__day{margin-bottom:12px}'
            .'.patches-changelog__day ul{margin:4px 0 0;padding-left:20px}</style>'
            .'<script>'.$scripts.'</script>'
        );
    }
}
