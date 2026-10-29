<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the two dark themes (Account > Appearance): the notification that shows on the screen (the toast, `.alert-growl`)
 * was a near-white box and the link of a warning or an error, in yellow (#FDB933) or red (#F86B4F), could not be read on it.
 *
 * Core designed the toast as the OPPOSITE of the page: `--layer-translucent-inverse` as the background and `--text-inverse` as the text, which is
 * a dark box with light text on a light page. In Dark and Solarized Dark the inverse layer is light (rgba(255,255,255,.8) and a cream), but
 * the colors of the links of a warning and of an error are fixed for a dark box. Here the toast follows the page instead: the theme's second
 * layer as the background (with a shadow, to stand out from a page of the same tone), the theme's own text color, and the yellow and red links
 * of core, which read well on it. The colored left border of core stays. The light themes keep core's dark toast.
 *
 * Injected the same way as DarkThemeButtonSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on every
 * admin page). The :root[theme=...] prefix makes the rules more specific than core's, so the order of the stylesheets does not matter.
 */
class DarkThemeToastSubscriber implements EventSubscriberInterface
{
    private const THEMES = ['dark', 'solarized-dark'];

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

        $rules = '';
        foreach (self::THEMES as $theme) {
            $root = ':root[theme="'.$theme.'"] .alert-growl';
            $rules .= $root.'{background:color-mix(in srgb,var(--layer-02) 94%,transparent);color:var(--text-primary);box-shadow:0 2px 8px rgba(0,0,0,.45)}'
                // the plain links and the close button follow the text; the links of a warning and of an error keep core's yellow and red
                .$root.':not(.alert-growl--warning):not(.alert-growl--error) a,'.$root.':not(.alert-growl--warning):not(.alert-growl--error) a:hover,'.$root.' .close{color:var(--text-primary)}';
        }

        $customContentEvent->addContent('<style>'.$rules.'</style>');
    }
}
