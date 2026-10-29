<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\EmailBundle\Entity\Email;

/**
 * The same as UserStatusSubscriber, for the email list: the active / inactive toggle next to the name (an icon that is easy to hit by
 * mistake, with the status told only by its color) becomes
 *  - a label at the start of the name ("Ativo" green, "Inativo" red, informative, not a link, all with the width of "Inativo");
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit the email.
 * Core's flow is kept: the menu item clicks core's icon (hidden), so the server checks the permission as it always did. An email
 * published but outside its publish dates (core's icon says pending / expired) counts as active, like its `isPublished` flag.
 *
 * The list has no hook that fits (the one at the name is inside the link): the script (Resources/js/list-status-toggle.js,
 * installEmail) makes the labels from the state of core's icons.
 */
class EmailStatusSubscriber extends AbstractListStatusSubscriber
{
    // the badge's own style (the pill, its width, hidden until placed) is AbstractListStatusSubscriber::BADGE_STYLE and SegmentStatusSubscriber::STYLE, on every page
    public const STYLE = 'table.email-list i[class*="email-publish-icon"]{display:none!important}'
        // the icons core prints next to the name (A/B test, translation, segment email, draft) are badges in the Status column (the script): hidden from the first paint
        .'table.email-list td:not(.col-patches-status) span[data-toggle="tooltip"]:has(>i:is(.ri-organization-chart,.ri-translate,.ri-pie-chart-line,.fa-file)){display:none!important}'
        .'a[data-email-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-email-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-email-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-email-status-action]:hover *{color:inherit!important}';

    protected function key(): string
    {
        return 'email';
    }

    protected function installFunction(): string
    {
        return 'installEmail';
    }

    protected function style(): string
    {
        return self::STYLE;
    }

    protected function route(): string
    {
        return 'mautic_email_index';
    }

    protected function supports(mixed $item): bool
    {
        return $item instanceof Email;
    }

    protected function mayEdit(object $item): bool
    {
        return $this->security->hasEntityAccess('email:emails:editown', 'email:emails:editother', $item->getCreatedBy());
    }
}
