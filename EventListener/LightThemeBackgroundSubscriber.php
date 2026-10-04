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

    /**
     * Core value in the comment, so a change is easy to compare. The layer
     * family (the `--layer` that dots, wells, tags and the feed line use) and
     * the subtle borders are a step darker than the page, so what was a faint
     * gray on white still stands out on the gray page.
     */
    private const VARIABLES = [
        '--background'               => '#f2f2f2', // #ffffff
        '--background-hover'         => '#e8e8e8', // #f1f1f1
        '--layer-01'                 => '#e0e0e0', // #f4f4f4
        '--layer-02'                 => '#f2f2f2', // #ffffff
        '--layer-hover-01'           => '#d4d4d4', // #e8e8e8
        '--layer-hover-02'           => '#e8e8e8', // #e8e8e8
        '--layer-selected-01'        => '#d0d0d0', // #e0e0e0
        '--layer-selected-hover-01'  => '#c4c4c4', // #cacaca
        '--layer-accent-01'          => '#d0d0d0', // #e0e0e0
        '--layer-accent-hover-01'    => '#c4c4c4', // #d1d1d1
        '--field-01'                 => '#eaeaea', // #f4f4f4
        '--field-02'                 => '#f2f2f2', // #fff
        '--field-hover-01'           => '#e0e0e0', // #e8e8e8
        '--field-hover-02'           => '#e8e8e8', // #e8e8e8
        '--border-subtle-01'         => '#d2d2d2', // #e0e0e0
        '--border-subtle-02'         => '#bcbcbc', // #c6c6c6
        '--table-line'               => '#b0b0b0', // (new) the lines between table rows
    ];

    /**
     * Surfaces the core paints with a fixed light gray (not a variable), which
     * were fine on white but vanish on the gray page: striped and hovered table
     * rows (#fafafa), the breadcrumb (#f5f5f5), the toggle switch's track
     * (#fafafa, with a #e5e5e5 ring) and the menu divider (#e5e5e5). They take
     * the theme's own layer / border variables instead. The switch rule is for
     * the unchecked state only, so the checked color is not overridden. The
     * table lines (the contact's History, every list) are the core's faint
     * --border-subtle, which is almost the color of a hovered row here
     * (#d2d2d2 on #d4d4d4) and vanished on hover: they take --table-line, a
     * step darker than both.
     */
    private const RULES = [
        '.table-striped>tbody>tr:nth-of-type(odd)'         => 'background-color:var(--layer-01)',
        '.table-striped>tbody>tr:nth-of-type(odd):hover'   => 'background-color:var(--layer-hover-01)',
        '.table-hover>tbody>tr:hover'                      => 'background-color:var(--layer-hover-01)',
        '.breadcrumb'                                      => 'background-color:var(--layer-01)',
        '.switch input:not(:checked)~.switch'              => 'background-color:var(--layer-01);box-shadow:inset 0 0 0 1px var(--border-subtle-02)',
        '.switch input:not(:checked)~.switch:after'        => 'border-color:var(--border-subtle-02)',
        '.nav .nav-divider'                                => 'background-color:var(--border-subtle)',
        '.table>tbody>tr>td'                               => 'border-top-color:var(--table-line)',
        '.table>tbody>tr>th'                               => 'border-top-color:var(--table-line)',
        '.table>tfoot>tr>td'                               => 'border-top-color:var(--table-line)',
        '.table>thead>tr>th'                               => 'border-bottom-color:var(--table-line)',
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

        $css = $root.'{'.$declarations.'}';

        foreach (self::RULES as $selector => $rule) {
            $css .= $root.' '.$selector.'{'.$rule.'}';
        }

        $css .= $root.' .bg-white'.$white
            .$root.' .bg-auto'.$white
            .$root.' .modal .box-layout .bg-auto'.$white;

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }
}
