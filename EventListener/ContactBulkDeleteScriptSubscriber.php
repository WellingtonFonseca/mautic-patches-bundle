<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the script of the bulk delete page (Resources/js/contact-bulk-delete.js) on the admin pages, like the other patches scripts
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left'). It only defines the hooks core calls when the page and its table load, so
 * on any other page it does nothing.
 */
class ContactBulkDeleteScriptSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent'];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/contact-bulk-delete.js')
            .';mauticPatchesContactBulkDeleteApi.install(mQuery,Mautic);</script>'
        );
    }
}
