<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A look change for every row menu (three dots) of the admin in the DARK themes: the Delete item (`.dropdown-menu > li a.danger`)
 * turns red on hover or focus, and core paints its text and icon `var(--text-on-color)`, white, which is hard to read on the light red
 * the dark themes use (2.3:1). They are black there. On Mautic 7 the item is styled through its id (`a#delete:hover`), which is stronger than a class: both are matched. The light themes keep core's white, which reads well on their strong red. The icon
 * and the text inherit the color of the item.
 *
 * Injected the same way as DarkThemeBackgroundSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on
 * every admin page). The `:root[theme=...]` prefix makes the rule more specific than core's, so the order of the stylesheets does not
 * matter.
 */
class MenuDeleteHoverSubscriber implements EventSubscriberInterface
{
    public const DARK_THEMES = ['dark', 'solarized-dark'];

    public static function style(): string
    {
        $css = '';
        foreach (self::DARK_THEMES as $theme) {
            // core 7 styles the item through its id (`a#delete:hover`, stronger than a class): both are matched
            foreach (['a.danger', 'a#delete'] as $item) {
                $root = ':root[theme="'.$theme.'"] .dropdown-menu>li '.$item;
                $css .= $root.':hover,'.$root.':focus{color:#000}'
                    .$root.':hover i,'.$root.':hover span,'.$root.':focus i,'.$root.':focus span{color:inherit}';
            }
        }

        return $css;
    }

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

        $customContentEvent->addContent('<style>'.self::style().'</style>');
    }
}
