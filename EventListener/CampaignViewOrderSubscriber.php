<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Not a bug, a layout choice for the campaign page (Campaigns > open one): the
 * statistics / map block comes first and the preview of the journey (with the
 * Actions and Contacts tabs) is pushed down the page, where users do not scroll
 * to. This moves the statistics block (the `.stats-menu` tabs with its date
 * range, and `.stats-menu__content`, the chart and the map) to after the
 * preview, so the journey is what shows first.
 *
 * The page is built by core's template, which this does not touch: a script
 * moves the two nodes once the page is in place — on the first load and after
 * every Mautic ajax page load (it wraps Mautic.onPageLoad, so core's own
 * campaign scripts have already run on the nodes) — and fires a window resize
 * so the charts fit their new spot. Moving a node keeps its ids and handlers,
 * so the tabs, the date filter and the map keep working. It only acts when the
 * page has the preview (`#preview-container`), so no other page is affected,
 * and it does nothing if the block is already below it.
 *
 * Injected the same way as CampaignBuilderOverlaySubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignViewOrderSubscriber implements EventSubscriberInterface
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
            .'if(window.mauticPatchesCampaignViewOrder){return;}'
            .'window.mauticPatchesCampaignViewOrder=true;'
            .'function reorder(){'
            .'var $preview=mQuery("#preview-container").closest(".tab-content");'
            .'var $menu=mQuery(".stats-menu");'
            .'var $stats=mQuery(".stats-menu__content");'
            .'if(!$preview.length||!$menu.length||!$stats.length){return;}'
            // already below the preview: nothing to do
            .'if($menu[0].compareDocumentPosition($preview[0])&Node.DOCUMENT_POSITION_PRECEDING){return;}'
            .'$preview.after($menu,$stats);'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;};'
            .'mQuery(reorder);'
            .'})();';
    }
}
