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

    /** A campaign that lets its contacts enter again carries the marker the script turns into the reentry badge. */
    protected function labelAttributes(object $item): string
    {
        return $item->allowRestart() ? ' data-restart="1"' : '';
    }

    protected function nameContext(): ?string
    {
        return 'campaign.name';
    }
}
