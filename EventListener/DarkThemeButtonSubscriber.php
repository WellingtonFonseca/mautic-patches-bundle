<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the dark theme (Account > Appearance): hovering
 * Save & Close or Cancel turns the button white with white text.
 *
 * Core styles a button "tertiary" not only by class (.btn-tertiary) but also
 * by position: the second .btn-primary in a row, and a .btn-secondary after
 * two .btn-primary — which is how Save & Close and Cancel look next to Apply
 * (and in every plugin that uses the same form buttons). Its hover and
 * pressed states hard-code `color: var(--text-on-color)`, which in the dark
 * theme is white, over the theme's own light tertiary background
 * (--button-tertiary-hover #f4f4f4, --button-tertiary-active #c6c6c6): a
 * contrast of 1.1:1 and 1.7:1. Only the dark theme is affected — the other
 * four keep a readable pair — so only it is overridden, with the theme's own
 * dark text variable, and never on a disabled button.
 *
 * Injected the same way as DarkThemeTableSubscriber (CoreEvents::
 * VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on every admin
 * page). The :root[theme=...] prefix makes these rules more specific than the
 * core ones, so the order of the stylesheets does not matter.
 */
class DarkThemeButtonSubscriber implements EventSubscriberInterface
{
    private const THEME = 'dark';

    private const BUTTONS = [
        '.btn-tertiary',
        '.btn-tertiary+.dropdown-toggle',
        '.btn-primary+.btn-primary:not(.dropdown-toggle)',
        '.btn-primary+.btn-primary+.btn-primary',
        '.btn-primary+.btn-primary+.btn-secondary',
    ];

    private const STATES = [':hover', ':active', ':focus'];

    // A disabled button (core disables them while a form is being sent) keeps core's own disabled look.
    private const ENABLED = ':not([disabled]):not(.disabled)';

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

        $selectors = [];
        foreach (self::BUTTONS as $button) {
            foreach (self::STATES as $state) {
                $selector    = ':root[theme="'.self::THEME.'"] '.$button.self::ENABLED.$state;
                $selectors[] = $selector;
                $selectors[] = $selector.' i';
            }
        }

        $customContentEvent->addContent('<style>'.implode(',', $selectors).'{color:var(--text-inverse)}</style>');
    }
}
