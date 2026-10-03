<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the dark themes (Account > Appearance): the
 * pages that lay out their content with `.bg-white` / `.bg-auto` columns
 * (the Custom Objects detail and form pages, for one) show almost the whole
 * screen in white, with dark text.
 *
 * app.css hard-codes both utility classes as `background-color: #fff
 * !important; border-color: #fff !important; color: #3c4650 !important`
 * (and `.modal .box-layout .bg-auto` as #fff), while the themes only change
 * CSS variables (`:root[theme="dark"]`), so they never follow the theme. Only
 * the two dark themes are overridden, with the same variables core's own
 * panels use (--background, --border-subtle) and --text-primary for the text,
 * still `!important` since the core rules are. Light themes keep the core look.
 *
 * Injected the same way as DarkThemeTableSubscriber. The :root[theme=...]
 * prefix makes these rules more specific than the core ones, so the order
 * of the stylesheets does not matter.
 */
class DarkThemeBackgroundSubscriber implements EventSubscriberInterface
{
    private const DARK_THEMES = ['dark', 'solarized-dark'];

    private const DECLARATIONS = '{background-color:var(--background)!important;border-color:var(--border-subtle)!important;color:var(--text-primary)!important}';

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
            $root = ':root[theme="'.$theme.'"]';

            $css .= $root.' .bg-white'.self::DECLARATIONS
                .$root.' .bg-auto'.self::DECLARATIONS
                .$root.' .modal .box-layout .bg-auto'.self::DECLARATIONS;
        }

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }
}
