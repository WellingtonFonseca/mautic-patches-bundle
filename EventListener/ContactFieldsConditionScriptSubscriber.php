<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the script of the rows of the campaign condition "Contact fields" (Resources/js/contact-fields-condition.js) on the
 * page: the form of the condition is loaded by ajax into the campaign builder, and its widget calls
 * mauticPatchesContactFields.init() when it arrives.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class ContactFieldsConditionScriptSubscriber implements EventSubscriberInterface
{
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

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/contact-fields-condition.js')
            .';mauticPatchesContactFieldsApi.install(mQuery);</script>'
        );
    }
}
