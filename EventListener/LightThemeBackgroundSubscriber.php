<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Softens the Light theme (Account > Appearance): the page, the panels and the
 * fields are pure white (`--background: #ffffff`, `--layer-02`, `--field-02`),
 * which blows out the screen. They become a light gray instead, keeping the
 * hierarchy of the layers (the layer-01 surfaces stay one step darker than the
 * page). Not an edit of core: the theme only defines CSS variables, so this
 * overrides those variables under the same `:root[theme="light"]` selector,
 * and the hard-coded white of `.bg-white` / `.bg-auto` (a core utility class,
 * `#fff !important`) follows them.
 *
 * Only the `light` theme is touched; Solarized Light, Freire and the dark
 * themes keep their own look. Every value is in VARIABLES, to tune the gray in
 * one place. Injected the same way as DarkThemeBackgroundSubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class LightThemeBackgroundSubscriber implements EventSubscriberInterface
{
    private const THEME = 'light';

    /** Core value in the comment, so a change is easy to compare. */
    private const VARIABLES = [
        '--background'       => '#f2f2f2', // #ffffff
        '--background-hover' => '#e8e8e8', // #f1f1f1
        '--layer-01'         => '#eaeaea', // #f4f4f4
        '--layer-02'         => '#f2f2f2', // #ffffff
        '--layer-hover-01'   => '#e0e0e0', // #e8e8e8
        '--layer-hover-02'   => '#e8e8e8', // #e8e8e8
        '--field-01'         => '#eaeaea', // #f4f4f4
        '--field-02'         => '#f2f2f2', // #fff
        '--field-hover-01'   => '#e0e0e0', // #e8e8e8
        '--field-hover-02'   => '#e8e8e8', // #e8e8e8
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

        $root = ':root[theme="'.self::THEME.'"]';

        $declarations = '';
        foreach (self::VARIABLES as $name => $value) {
            $declarations .= $name.':'.$value.';';
        }

        $white = '{background-color:var(--background)!important;border-color:var(--border-subtle)!important}';

        $css = $root.'{'.$declarations.'}'
            .$root.' .bg-white'.$white
            .$root.' .bg-auto'.$white
            .$root.' .modal .box-layout .bg-auto'.$white;

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }
}
