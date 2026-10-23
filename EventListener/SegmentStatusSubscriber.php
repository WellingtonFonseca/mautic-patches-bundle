<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A usability change: the active / inactive toggle of the segment list was a small icon right before the name, easy to hit by
 * mistake, with the status told only by its color. Now:
 *  - a label at the start of the name tells the status ("Ativo" green, "Inativo" red); it is only informative, not a link; all of
 *    them have the width of "Inativo" (measured by the script), so they line up;
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
    // the label is placed at the start of the name by the script; until then it is invisible, so it does not flash at the end
    // the description core prints under the name in the segment and campaign rows (the rows with this label inside) is hidden too
    public const STYLE = 'i[class*="lead-list-publish-icon"]{display:none!important}'
        .'td:has(.segment-status-label) > div.text-muted{display:none!important}'
        // "Atualizado em ..." under the name: as large as the rest of the table (core's <small> is 11.9px)
        .'.segment-last-built small{font-size:14px}'
        // the contact count badge ("Ver 6 Contatos") has the size of the table text (core's label is 11px)
        .'a.col-count{font-size:14px}'
        .'.segment-status-label{margin-right:var(--spacing-03);box-sizing:border-box;font-size:14px;min-width:var(--segment-status-width,0)}.segment-status-label:not([data-placed]){visibility:hidden}'
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
            $config = [
                'active'     => $this->translator->trans('mautic.core.form.active'),
                'inactive'   => $this->translator->trans('mautic.core.form.inactive'),
                'activate'   => $this->translator->trans('mautic.patches.segment.activate'),
                'deactivate' => $this->translator->trans('mautic.patches.segment.deactivate'),
                'style'      => self::STYLE,
            ];
            $customContentEvent->addContent(
                '<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-status-toggle.js')
                .';mauticPatchesSegmentStatusApi.install(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
            );

            return;
        }

        $segment = $customContentEvent->getVars()['item'] ?? null;
        if ('segment.name' !== $context || !$segment instanceof LeadList) {
            return;
        }

        $published = $segment->isPublished();
        $customContentEvent->addContent(sprintf(
            '<span class="label %s segment-status-label" data-segment-id="%d" data-status="%s" data-alias="%s">%s</span>',
            $published ? 'label-success' : 'label-danger',
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
