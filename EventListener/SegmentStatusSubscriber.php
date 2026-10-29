<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadList;

/**
 * A usability change: the active / inactive toggle of the segment list was a small icon right before the name, easy to hit by
 * mistake, with the status told only by its color. Now:
 *  - a label at the start of the name tells the status ("Ativo" green, "Inativo" red); it is only informative, not a link; all of
 *    them have the same fixed width (76px, as the n8n badge's), so they line up;
 *  - the icon is hidden, and so is the alias after the name ("Name (alias)"), which is long, made from the name and says nothing:
 *    the label carries it (data-alias) and the script takes it off the text;
 *  - the three-dots menu of the row has "Desativar" / "Ativar" as its FIRST item (the last one is Delete, which nobody should
 *    reach by mistake). Its hover is red for Desativar and green for Ativar, the color of the status it leads to. Only for who
 *    may edit the segment; the server checks it again, as it does for core's icon.
 * Resources/js/list-status-toggle.js sends the request and updates the label and the item.
 *
 * Injected the same way as SegmentLastBuiltSubscriber ('segment.name') and SegmentDetailsButtonSubscriber (the row menu);
 * the script and style on 'page.header.left'.
 */
class SegmentStatusSubscriber extends AbstractListStatusSubscriber
{
    // The badge stays invisible until the script has placed it at the start of the name, so it does not flash at the end; the
    // description core prints under the name in the segment, campaign and email rows (the rows with a badge inside) is hidden.
    public const STYLE = self::BADGE_STYLE.self::ICON_BADGE_STYLE
        .'i[class*="lead-list-publish-icon"]{display:none!important}'
        // the label leaves the name cell for the Status column (the script), so the rule that found the description by the label is by table
        .'#leadListTable td > div:is(.text-muted,.text-secondary),#campaignTable td > div:is(.text-muted,.text-secondary),table.email-list td > div:is(.text-muted,.text-secondary){display:none!important}'
        // the icons core prints next to the name (global, filters, the two warnings) are shown as badges in the Status column by the script: hidden from the first paint
        .'#leadListTable td:not(.col-patches-status) :is(i.ri-group-2-fill,i.ri-filter-2-fill,label:has(>i.ri-error-warning-line),label:has(>i.ri-time-line)){display:none!important}'
        // "Atualizado em ..." under the name: as large as the rest of the table (core's <small> is 11.9px)
        .'.segment-last-built small{font-size:14px}'
        .'.patches-status-label{margin-right:var(--spacing-03);box-sizing:border-box;min-width:76px}.patches-status-label:not([data-placed]){visibility:hidden}'
        .'a[data-segment-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-segment-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-segment-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-segment-status-action]:hover *{color:inherit!important}'
        .'li:has(>a[hidden][href*="/s/segment-rebuild/"]){display:none!important}'; // Update of an inactive segment, shown by the script on activation

    protected function key(): string
    {
        return 'segment';
    }

    protected function installFunction(): string
    {
        return 'install';
    }

    protected function style(): string
    {
        return self::STYLE;
    }

    protected function route(): string
    {
        return 'mautic_segment_index';
    }

    protected function supports(mixed $item): bool
    {
        return $item instanceof LeadList;
    }

    protected function mayEdit(object $item): bool
    {
        return $this->security->hasEntityAccess('lead:lists:editown', 'lead:lists:editother', $item->getCreatedBy());
    }

    protected function nameContext(): ?string
    {
        return 'segment.name';
    }

    protected function labelAttributes(object $item): string
    {
        return sprintf(' data-alias="%s"', htmlspecialchars((string) $item->getAlias(), ENT_QUOTES));
    }
}
