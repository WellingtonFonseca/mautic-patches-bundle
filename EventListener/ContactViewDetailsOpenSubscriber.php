<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Not a bug, a usability choice for the contact page (Contacts > open one):
 * the "Details" block (`#lead-details`, the contact's fields) starts closed and
 * needs a click on its toggler every time. This opens it as soon as the page
 * is in place, and the toggler still closes and opens it.
 *
 * Core's template is not touched: a script adds the Bootstrap `in` class to the
 * block and drops `collapsed` from the toggler (so the caret matches), on the
 * first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad).
 * A block is opened once only (a data flag), so running again later never
 * reopens one the user closed. Pages without `#lead-details` are not affected.
 *
 * Injected the same way as CampaignViewOrderSubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class ContactViewDetailsOpenSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<script>'.self::script().'</script>');
    }

    public static function script(): string
    {
        return '(function(){'
            .'if(window.mauticPatchesContactDetailsOpen){return;}'
            .'window.mauticPatchesContactDetailsOpen=true;'
            .'function open(){'
            .'var $block=mQuery("#lead-details");'
            .'if(!$block.length||$block.data("mauticPatchesOpened")){return;}'
            .'$block.data("mauticPatchesOpened",true);'
            .'$block.addClass("in");'
            .'mQuery("[data-target=\'#lead-details\']").removeClass("collapsed");'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);open();return result;};'
            .'mQuery(open);'
            .'})();';
    }
}
