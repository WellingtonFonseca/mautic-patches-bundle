<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A layout choice for the email page (Channels > Emails > open one): the Estatísticas / Mapa block comes first and pushes the
 * Contagem de cliques / Contatos tabs down the page. This moves the statistics block (the `.stats-menu` tabs with its date range,
 * and `.stats-menu__content`, the chart and the map) to after the tabs' content (the `.tab-content` that holds
 * `#clicks-container`), so the clicks and the contacts show first.
 *
 * Same technique as CampaignViewOrderSubscriber: core's template is not touched, a script moves the nodes on the first load and
 * after every Mautic ajax page load (it wraps Mautic.onPageLoad, so core's scripts have already run on them) and fires a window
 * resize so the charts fit their new spot. Moving a node keeps its ids and handlers, so the tabs, the date range and the map keep
 * working. It only acts on a page that has `#clicks-container` and the statistics block, and does nothing if the block is already
 * below the tabs.
 *
 * Injected on 'page.header.left' (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT), like the other page scripts.
 */
class EmailViewOrderSubscriber implements EventSubscriberInterface
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
            .'if(window.mauticPatchesEmailViewOrder){return;}'
            .'window.mauticPatchesEmailViewOrder=true;'
            .'function reorder(){'
            .'var $tabs=mQuery("#clicks-container").closest(".tab-content");'
            .'var $menu=mQuery(".stats-menu");'
            .'var $stats=mQuery(".stats-menu__content");'
            .'if(!$tabs.length||!$menu.length||!$stats.length){return;}'
            // already below the tabs: nothing to do
            .'if($menu[0].compareDocumentPosition($tabs[0])&Node.DOCUMENT_POSITION_PRECEDING){return;}'
            .'$tabs.after($menu,$stats);'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;};'
            .'mQuery(reorder);'
            .'})();';
    }
}
