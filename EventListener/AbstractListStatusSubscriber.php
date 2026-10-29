<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the status subscribers of the Mautic lists (segment, campaign, user, email) have in common: the script and style on
 * 'page.header.left', the "Ativo" / "Inativo" label at the start of the name (where the list has a hook for it) and "Desativar" /
 * "Ativar" as the FIRST item of the row menu. Each list names its entity, its route, who may edit and the install function of
 * Resources/js/list-status-toggle.js. The data-* names the script reads come from key(): data-<key>-id and data-<key>-status-action.
 */
abstract class AbstractListStatusSubscriber implements EventSubscriberInterface
{
    // The status badge of the Mautic lists (segment, campaign, user, email) and the contact count of the segment list. It is the twin of N8nDispatchBundle's badge
    // (.n8ndispatch-badge in n8ndispatch.css), with the same modifiers: a 13px bold uppercase pill, white text, no
    // icon, 76px wide, green (--production) / red (--failed) / orange while it works (--pending). Keep the two in step.
    // It does not use core's .label, so core's size and colors do not reach it; .patches-status-label is only the hook of the script.
    public const BADGE_STYLE = '.patches-badge{display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:2px 10px;border-radius:11px;'
        .'color:#fff;font-size:13px;font-weight:600;line-height:normal;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}'
        .'.patches-badge--production{background-color:#43a047}'
        .'.patches-badge--failed{background-color:#e53935}'
        .'.patches-badge--pending{background-color:#fb8c00}'
        // the translucent ones: the contact count of the segment list (--info has contacts, --neutral is empty)
        .'.patches-badge--info{background-color:rgba(30,136,229,.16);color:#1e88e5;box-shadow:inset 0 0 0 1px rgba(30,136,229,.45)}'
        .'.patches-badge--neutral{background-color:rgba(120,120,120,.16);color:var(--text-secondary,#6f6f6f);box-shadow:inset 0 0 0 1px rgba(120,120,120,.4)}'
        // Mautic 7 prints the contact count as a span with the link inside: the link takes the badge's color
        .'.patches-badge a{color:inherit}';

    /**
     * The badges of the Status column of the lists (segment, campaign, user, email: Resources/js/list-status-toggle.js, addStatusColumn): the status and,
     * in the campaign list the reentry one, in the segment list the global / filters / warning ones, beside it are 22px circles with a play / stop / reload icon; on hover one opens to the right and
     * shows the word, as the status badge of the n8n campaign card. They sit side by side in the flow of the cell; opening one widens the column and the browser lays out the table (no script). The 76px minimum of the word badge does not apply to them.
     */
    public const ICON_BADGE_STYLE = '#leadListTable td,#campaignTable td,#userTable td,table.email-list td,#leadTable td{white-space:nowrap}'
        .'td.col-patches-status .patches-status-box{display:flex;align-items:center;gap:6px;height:22px}'
        .'td.col-patches-status :is(.patches-status-label,.patches-extra-badge){position:relative;z-index:2;flex:none;display:inline-flex;box-sizing:border-box;height:22px;min-width:0;max-width:22px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;transition:max-width .2s ease,box-shadow .2s ease}'
        .'td.col-patches-status :is(.patches-status-label,.patches-extra-badge):hover{z-index:3;max-width:140px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0}'
        .'td.col-patches-status :is(.patches-status-label,.patches-extra-badge)>i{flex:0 0 22px;width:22px;height:22px;display:flex;align-items:center;justify-content:center}'
        .'td.col-patches-status .patches-icon-badge__text{padding:0 10px 0 0;opacity:0;transition:opacity .15s ease .05s}'
        .'td.col-patches-status :is(.patches-status-label,.patches-extra-badge):hover .patches-icon-badge__text{opacity:1}'
        // the badges beside the status: informative blue (reentry, global, filters; the red ones are patches-badge--failed); a long word (the warnings of the segment list) gets more room
        .'.patches-badge--flag,.patches-badge--restart{background-color:#1e88e5}'
        .'td.col-patches-status .patches-extra-badge--wide:hover{max-width:260px}'
        // the badges of the email list: one color for the type (campaign and broadcast share it), one for each of A/B test, translation and draft
        .'.patches-badge--emailtype{background-color:#00897b}.patches-badge--abtest{background-color:#7e57c2}'
        .'.patches-badge--translation{background-color:#4285f4}.patches-badge--draft{background-color:#546e7a}';

    public function __construct(
        protected TranslatorInterface $translator,
        protected CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    /** segment, campaign, user, email: the middle of the data-<key>-id and data-<key>-status-action attributes. */
    abstract protected function key(): string;

    /** The function of mauticPatchesListStatusApi that installs the list (install, installCampaign, ...). */
    abstract protected function installFunction(): string;

    /** The CSS the list needs (its STYLE constant). */
    abstract protected function style(): string;

    /** The route of the list page, where the row menu item is added. */
    abstract protected function route(): string;

    /** Whether the item of the row is of this list's entity. */
    abstract protected function supports(mixed $item): bool;

    /** Whether the user may change the status of this item; the server checks it again, as it does for core's icon. */
    abstract protected function mayEdit(object $item): bool;

    /** The custom content hook that prints inside the name link ('segment.name'), or null when the list has none (the script makes the labels). */
    protected function nameContext(): ?string
    {
        return null;
    }

    /** More attributes of the label, already escaped, with a leading space (the segment's alias). */
    protected function labelAttributes(object $item): string
    {
        return '';
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        $context = $customContentEvent->getContext();

        if ('page.header.left' === $context) {
            $customContentEvent->addContent(StatusScript::html($this->translator, $this->style(), $this->installFunction()));

            return;
        }

        $item = $customContentEvent->getVars()['item'] ?? null;
        if (null === $item || $this->nameContext() !== $context || !$this->supports($item)) {
            return;
        }

        $published = $item->isPublished();
        $customContentEvent->addContent(sprintf(
            '<span class="patches-badge %s patches-status-label" data-%s-id="%d" data-status="%s"%s>%s</span>',
            $published ? 'patches-badge--production' : 'patches-badge--failed',
            $this->key(),
            $item->getId(),
            $published ? 'published' : 'unpublished',
            $this->labelAttributes($item),
            htmlspecialchars($this->translator->trans($published ? 'mautic.core.form.active' : 'mautic.core.form.inactive'), ENT_QUOTES)
        ));
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $item = $event->getItem();

        if (!$this->supports($item)
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || $this->route() !== $event->getRoute()
            || !$this->mayEdit($item)) {
            return;
        }

        $key       = $this->key();
        $published = $item->isPublished();
        $event->addButton(
            [
                'attr' => [
                    // not 'ajax': core would add that and navigate; the script sends the request or clicks core's icon
                    'data-toggle'                => $key.'-status',
                    'data-'.$key.'-id'            => $item->getId(),
                    'data-'.$key.'-status-action' => $published ? 'deactivate' : 'activate',
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
