<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the dark themes (Account > Appearance): striped
 * tables show a near-white row between the dark ones, and so does a
 * highlighted ("new") row.
 *
 * Bootstrap's libraries.css hard-codes `.table-striped > tbody >
 * tr:nth-of-type(odd) { background-color: #fafafa }` (and the same #fafafa on
 * hover), while the themes only change CSS variables (`:root[theme="dark"]`),
 * so that rule never follows the theme. Only the two dark themes are
 * overridden, with the theme's own variables: odd rows use --layer-01 and
 * their hover --layer-hover-01 (falling back to --background-hover). Light
 * themes keep the core look.
 *
 * Same cause for the rows a list marks as new (`tr.warning`, e.g. the contacts
 * added since the list was loaded, shown after clicking the list's refresh):
 * core hard-codes a pale yellow, #fcf8e3 (#faf2cc on hover), on their cells, which
 * with the dark theme's light text cannot be read. In the dark themes they get a
 * dark tint of the theme's own warning color (--support-warning) over the row
 * layer, so the row still stands out and the text keeps its color.
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

        foreach (self::DARK_THEMES as $theme) {
            $cell      = ':root[theme="'.$theme.'"] .table>tbody>tr.warning>td,:root[theme="'.$theme.'"] .table>tbody>tr.warning>th,'
                .':root[theme="'.$theme.'"] .table>tbody>tr>td.warning,:root[theme="'.$theme.'"] .table>tbody>tr>th.warning';
            $cellHover = ':root[theme="'.$theme.'"] .table-hover>tbody>tr.warning:hover>td,:root[theme="'.$theme.'"] .table-hover>tbody>tr.warning:hover>th,'
                .':root[theme="'.$theme.'"] .table-hover>tbody>tr:hover>.warning';
            $css .= $cell.'{background-color:color-mix(in srgb,var(--support-warning) 16%,var(--layer-01))}'
                .$cellHover.'{background-color:color-mix(in srgb,var(--support-warning) 26%,var(--layer-01))}';
        }

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }
}
