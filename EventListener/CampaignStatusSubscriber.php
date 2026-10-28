<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;

/**
 * The same as SegmentStatusSubscriber, for the campaign list: the active / inactive toggle next to the name (an icon that is easy to hit
 * by mistake, with the status told only by its color) becomes
 *  - a label in a "Status" column of its own, right after the menu column (the script adds the column and moves the label there):
 *    an icon in a circle (play on green = active, stop on red = inactive, informative, not a link) that opens on hover to show the word
 *    ("Ativo" / "Inativo"), the way the status badge of the n8n campaign card does;
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit the campaign.
 * Core's flow is kept: the menu item clicks core's icon (hidden), so deactivating asks for the confirmation of core
 * (Mautic.confirmationCampaignPublishStatus) and activating does not. The label follows core's icon. See
 * Resources/js/list-status-toggle.js (installCampaign).
 *
 * The label goes in the 'campaign.name' hook, which core prints INSIDE the name link: the script moves it out, into the Status column.
 * Injected like SegmentStatusSubscriber.
 */
class CampaignStatusSubscriber extends AbstractListStatusSubscriber
{
    // the badge's own style (the pill, its width, hidden until placed) is AbstractListStatusSubscriber::BADGE_STYLE and SegmentStatusSubscriber::STYLE, on every page
    public const STYLE = 'tr i[class*="campaign-publish-icon"]{display:none!important}'
        // the label leaves the name cell for its own column (the script), so SegmentStatusSubscriber's rule that finds the description by the label no longer reaches it
        .'#campaignTable td > div.text-muted{display:none!important}'
        // the label of the Status column is a 22px circle with the icon; on hover it opens to the right and shows the word, over the next cell (absolute, so the column does not move), as the n8n badge of the campaign card
        .'td.col-campaign-status{position:relative!important;min-width:60px}'
        .'td.col-campaign-status .patches-status-label{position:absolute;top:50%;left:var(--spacing-04,12px);transform:translateY(-50%);z-index:2;box-sizing:border-box;height:22px;min-width:0;max-width:22px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;transition:max-width .2s ease,box-shadow .2s ease}'
        .'td.col-campaign-status .patches-status-label:hover{max-width:140px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0}'
        .'td.col-campaign-status .patches-status-label>i{flex:0 0 22px;width:22px;height:22px;display:flex;align-items:center;justify-content:center}'
        .'td.col-campaign-status .patches-status-label__text{padding:0 10px 0 0;opacity:0;transition:opacity .15s ease .05s}'
        .'td.col-campaign-status .patches-status-label:hover .patches-status-label__text{opacity:1}'
        .'a[data-campaign-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-campaign-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-campaign-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-campaign-status-action]:hover *{color:inherit!important}'
        .'li:has(>a[hidden][href*="/s/campaign-rebuild/"]){display:none!important}'; // Update of an inactive campaign, shown by the script on activation

    protected function key(): string
    {
        return 'campaign';
    }

    protected function installFunction(): string
    {
        return 'installCampaign';
    }

    protected function style(): string
    {
        return self::STYLE;
    }

    protected function route(): string
    {
        return 'mautic_campaign_index';
    }

    protected function supports(mixed $item): bool
    {
        return $item instanceof Campaign;
    }

    protected function mayEdit(object $item): bool
    {
        return $this->security->hasEntityAccess('campaign:campaigns:editown', 'campaign:campaigns:editother', $item->getCreatedBy());
    }

    protected function nameContext(): ?string
    {
        return 'campaign.name';
    }
}
