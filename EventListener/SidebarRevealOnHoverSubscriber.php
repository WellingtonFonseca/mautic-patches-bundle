<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Not a bug, a usability choice: the left menu of the admin gives its 230px to the page. Only a 15px strip with a play arrow stays; the
 * mouse over the strip shows the menu on top of the page, and the menu hides again when the mouse leaves it. Pure CSS, no state.
 * Only from core's screen-sm (768px) up: below it core already hides the menu behind its own toggle.
 */
class SidebarRevealOnHoverSubscriber implements EventSubscriberInterface
{
    public const STRIP_WIDTH = 15;

    // core's @sidebar-left-width (variables.less)
    public const MENU_WIDTH = 230;

    // remixicon ri-play-fill
    private const PLAY_ICON = '\f00a';

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

    public static function style(): string
    {
        $menu   = ':root .app-sidebar.sidebar-left';
        $hidden = self::MENU_WIDTH - self::STRIP_WIDTH;

        return '@media (min-width:768px){'
            .$menu.'{transform:translateX(-'.$hidden.'px);transition:transform .15s ease}'
            .$menu.':hover{transform:none}'
            .$menu.'::after{content:"'.self::PLAY_ICON.'";font-family:remixicon;position:absolute;top:0;right:0;bottom:0;width:'.self::STRIP_WIDTH.'px;'
                .'display:flex;align-items:center;justify-content:center;font-size:16px;z-index:10;background:var(--layer);color:var(--text-secondary);'
                .'box-shadow:inset -1px 0 0 0 rgba(0,0,0,.05)}'
            .$menu.':hover::after{display:none}'
            .$menu.'~#app-content,'.$menu.'~#app-footer{margin-left:'.self::STRIP_WIDTH.'px}'
            .'}';
    }
}
