<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the dark themes (Account > Appearance): striped
 * tables show a near-white row between the dark ones.
 *
 * Bootstrap's libraries.css hard-codes `.table-striped > tbody >
 * tr:nth-of-type(odd) { background-color: #fafafa }` (and the same #fafafa on
 * hover), while the themes only change CSS variables (`:root[theme="dark"]`),
 * so that rule never follows the theme. Only the two dark themes are
 * overridden, with the theme's own variables: odd rows use --layer-01 and
 * their hover --layer-hover-01 (falling back to --background-hover). Light
 * themes keep the core look.
 *
 * Injected the same way as CampaignBuilderOverlaySubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on
 * every admin page). The :root[theme=...] prefix makes these rules more
 * specific than the core ones, so the order of the stylesheets does not matter.
 */
class DarkThemeTableSubscriber implements EventSubscriberInterface
{
    private const DARK_THEMES = ['dark', 'solarized-dark'];

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

        $css = '';
        foreach (self::DARK_THEMES as $theme) {
            $row = ':root[theme="'.$theme.'"] .table-striped>tbody>tr:nth-of-type(odd)';
            $css .= $row.'{background-color:var(--layer-01)}'
                .$row.':hover{background-color:var(--layer-hover-01,var(--background-hover))}';
        }

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }
}
