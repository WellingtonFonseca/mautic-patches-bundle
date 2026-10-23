<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
 * Resources/js/segment-status-toggle.js sends the request and updates the label and the item.
 *
 * Injected the same way as SegmentLastBuiltSubscriber ('segment.name') and SegmentDetailsButtonSubscriber (the row menu);
 * the script and style on 'page.header.left'.
 */
class SegmentStatusSubscriber implements EventSubscriberInterface
{
    // The status badge of the Mautic lists (segment, campaign, user, email) and the contact count of the segment list. It is the twin of N8nDispatchBundle's badge
    // (.n8ndispatch-timeline-badge in campaign-status-badge.css), with the same modifiers: a 13px bold uppercase pill, white text, no
    // icon, 76px wide, green (--production) / red (--failed) / orange while it works (--pending). Keep the two in step.
    // It does not use core's .label, so core's size and colors do not reach it; .segment-status-label is only the hook of the script.
    public const BADGE_STYLE = '.patches-badge{display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:2px 10px;border-radius:11px;'
        .'color:#fff;font-size:13px;font-weight:600;line-height:normal;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}'
        .'.patches-badge--production{background-color:#43a047}'
        .'.patches-badge--failed{background-color:#e53935}'
        .'.patches-badge--pending{background-color:#fb8c00}'
        // Mautic 7 prints the contact count as a span with the link inside: the link takes the badge's color
        .'.patches-badge a{color:inherit}'
        // the translucent ones: the contact count of the segment list (--info has contacts, --neutral is empty)
        .'.patches-badge--info{background-color:rgba(30,136,229,.16);color:#1e88e5;box-shadow:inset 0 0 0 1px rgba(30,136,229,.45)}'
        .'.patches-badge--neutral{background-color:rgba(120,120,120,.16);color:var(--text-secondary,#6f6f6f);box-shadow:inset 0 0 0 1px rgba(120,120,120,.4)}';

    // The badge stays invisible until the script has placed it at the start of the name, so it does not flash at the end; the
    // description core prints under the name in the segment, campaign and email rows (the rows with a badge inside) is hidden.
    public const STYLE = self::BADGE_STYLE
        .'i[class*="lead-list-publish-icon"]{display:none!important}'
        .'td:has(.segment-status-label) > div:is(.text-muted,.text-secondary){display:none!important}'
        // "Atualizado em ..." under the name: as large as the rest of the table (core's <small> is 11.9px)
        .'.segment-last-built small{font-size:14px}'
        .'.segment-status-label{margin-right:var(--spacing-03);box-sizing:border-box;min-width:76px}.segment-status-label:not([data-placed]){visibility:hidden}'
        .'a[data-segment-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-segment-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-segment-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-segment-status-action]:hover *{color:inherit!important}';

    public function __construct(
        private TranslatorInterface $translator,
        private CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        $context = $customContentEvent->getContext();

        if ('page.header.left' === $context) {
            $customContentEvent->addContent(StatusScript::html($this->translator, self::STYLE, 'install'));

            return;
        }

        $segment = $customContentEvent->getVars()['item'] ?? null;
        if ('segment.name' !== $context || !$segment instanceof LeadList) {
            return;
        }

        $published = $segment->isPublished();
        $customContentEvent->addContent(sprintf(
            '<span class="patches-badge %s segment-status-label" data-segment-id="%d" data-status="%s" data-alias="%s">%s</span>',
            $published ? 'patches-badge--production' : 'patches-badge--failed',
            $segment->getId(),
            $published ? 'published' : 'unpublished',
            htmlspecialchars((string) $segment->getAlias(), ENT_QUOTES),
            htmlspecialchars($this->translator->trans($published ? 'mautic.core.form.active' : 'mautic.core.form.inactive'), ENT_QUOTES)
        ));
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $segment = $event->getItem();

        if (!$segment instanceof LeadList
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_segment_index' !== $event->getRoute()
            || !$this->security->hasEntityAccess('lead:lists:editown', 'lead:lists:editother', $segment->getCreatedBy())) {
            return;
        }

        $published = $segment->isPublished();
        $event->addButton(
            [
                'attr' => [
                    // not 'ajax': core would add that and navigate; the script sends the request
                    'data-toggle'                => 'segment-status',
                    'data-segment-id'            => $segment->getId(),
                    'data-segment-status-action' => $published ? 'deactivate' : 'activate',
                    'href'                       => 'javascript:void(0);',
                ],
                'btnText'   => $this->translator->trans($published ? 'mautic.patches.segment.deactivate' : 'mautic.patches.segment.activate'),
                'iconClass' => $published ? 'ri-close-circle-line' : 'ri-checkbox-circle-line',
                'priority'  => 300, // first in the row menu: above Details (250), Edit and, far below, Delete
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
