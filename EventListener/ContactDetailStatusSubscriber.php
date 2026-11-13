<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Email / SMS / HSM badges of the contact list's Status column (ContactStatusSubscriber), copied to the contact page: three circles left of
 * the Editar button. The circles are the list's badges grown to the height of the round button beside them (40px, the list has 22px); the icon and
 * the word are 14px, the size of the text of core's buttons (the full 1.82 factor gave 23.6px, which looked too big). The script is Resources/js/contact-detail-badges.js.
 */
class ContactDetailStatusSubscriber implements EventSubscriberInterface
{
    private const SCRIPT = 'Resources/js/contact-detail-badges.js';

    // 40px is the height of core's buttons in the toolbar; the 8px gap between them is a 5px margin plus the space between inline buttons (about 3px), so the box has the 5px margin. Scoped to the box, so the list's badges keep their size.
    public const STYLE = '.patches-contact-badges{display:inline-flex;align-items:center;gap:8px;height:40px;margin-right:5px;vertical-align:top}'
        .'.patches-contact-badges .patches-extra-badge{position:relative;z-index:2;flex:none;display:inline-flex;box-sizing:border-box;height:40px;min-width:0;max-width:40px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;border-radius:20px;font-size:14px;transition:max-width .2s ease,box-shadow .2s ease}'
        .'.patches-contact-badges .patches-extra-badge:hover{z-index:3;max-width:140px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0}'
        .'.patches-contact-badges .patches-extra-badge>i{flex:0 0 40px;width:40px;height:40px;display:flex;align-items:center;justify-content:center}'
        .'.patches-contact-badges .patches-icon-badge__text{padding:0 14px 0 0;opacity:0;transition:opacity .15s ease .05s}'
        .'.patches-contact-badges .patches-extra-badge:hover .patches-icon-badge__text{opacity:1}';

    public function __construct(private TranslatorInterface $translator)
    {
    }

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

        // core passes the contact as 'lead' on its page; the other pages that fire this hook (lists, segments) do not need the script
        if (!(($customContentEvent->getVars()['lead'] ?? null) instanceof Lead)) {
            return;
        }

        $customContentEvent->addContent(StatusScript::html($this->translator, self::STYLE, 'installContactDetail', self::SCRIPT));
    }
}
