<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Status column of the contact list: one icon badge per channel (email, SMS, HSM), green when the contact can receive on it and red
 * when it is on the Do Not Contact list of the channel (bounce, unsubscribe or manual). It replaces the small red label core prints beside the
 * name, which is hidden from the first paint. HSM has no Do Not Contact channel of its own (the n8n plugin checks `sms` for SMS and HSM), so
 * its badge follows `sms`.
 *
 * Done by the script (Resources/js/list-status-toggle.js, installContact) from the labels core already prints, so no query is added; only the
 * list at /s/contacts gets the column. The look is the icon badge of the other Status columns (AbstractListStatusSubscriber::ICON_BADGE_STYLE).
 */
class ContactStatusSubscriber implements EventSubscriberInterface
{
    // core 7's red labels of the email (mail icon) and the other channels (chat icon, read as sms) (dnc_small.html.twig) beside the name: the column shows them instead. Only in a table that HAS the
    // column (the contacts tab of a segment or campaign, the company's contacts and the other channels keep core's labels) and from the moment it is there
    public const STYLE = '#leadTable:has(th.col-contact-status) td:not(.col-patches-status) .label-danger:has(>i.ri-mail-line,>i.ri-chat-1-line){display:none!important}';

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

        $customContentEvent->addContent(StatusScript::html($this->translator, self::STYLE, 'installContact'));
    }
}
