<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Not a bug, a usability choice: the left menu of the admin gives its 230px to the page. Only a 15px strip with a play arrow stays; the
 * mouse over the strip shows the menu on top of the page, and the menu hides again when the mouse leaves it. The keyboard focus in the
 * menu opens it too, or a tabbed item would be off screen: :has(:focus-visible), not :focus-within, because a clicked link keeps the
 * focus (the menu navigates by ajax) and :focus-within would hold the menu open after the mouse left. Pure CSS, no state.
 * Only from core's screen-sm (768px) up: below it core already hides the menu behind its own toggle.
 *
 * In the <head> (CustomAssetsEvent), not on 'page.header.left': that hook is inside #app-content, which the browser paints before the
 * <style> further down arrives, so core's 0.3s margin transition slid every loaded page from 230px to 15px. The assets event fires on
 * every request (API, tracking pixel, landing pages): only the admin (/s/) gets the rule.
 */
class SidebarRevealOnHoverSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public const STRIP_WIDTH = 15;

    // core's @sidebar-left-width (variables.less)
    public const MENU_WIDTH = 230;

    // remixicon ri-play-fill
    private const PLAY_ICON = '\f00a';

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => 'injectAssets',
        ];
    }

    public function injectAssets(CustomAssetsEvent $assetsEvent): void
    {
        if (!str_starts_with((string) $this->requestStack->getMainRequest()?->getPathInfo(), '/s/')) {
            return;
        }

        $assetsEvent->addStyleDeclaration(self::style());
    }

    public static function style(): string
    {
        $menu   = ':root .app-sidebar.sidebar-left';
        $hidden = self::MENU_WIDTH - self::STRIP_WIDTH;

        return '@media (min-width:768px){'
            .$menu.'{transform:translateX(-'.$hidden.'px);transition:transform .15s ease}'
            .$menu.':hover,'.$menu.':has(:focus-visible){transform:none}'
            .$menu.'::after{content:"'.self::PLAY_ICON.'";font-family:remixicon;position:absolute;top:0;right:0;bottom:0;width:'.self::STRIP_WIDTH.'px;'
                .'display:flex;align-items:center;justify-content:center;font-size:16px;z-index:10;background:var(--layer);color:var(--text-secondary);'
                .'box-shadow:inset -1px 0 0 0 rgba(0,0,0,.05)}'
            .$menu.':hover::after,'.$menu.':has(:focus-visible)::after{display:none}'
            .$menu.'~#app-content,'.$menu.'~#app-footer{margin-left:'.self::STRIP_WIDTH.'px}'
            .'}';
    }
}
