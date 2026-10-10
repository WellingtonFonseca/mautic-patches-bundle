<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A layout choice for the segment page (Segments > open one): the statistics chart comes first and pushes the
 * contacts (the table, the reason to open a segment) down the page. This moves the chart's block (the `.pa-md`
 * wrapper around the "Estatísticas" panel with its date range) to after the tabs' content (`#contacts-container`
 * and its siblings), so the contacts show first and the chart ends the page.
 *
 * Same technique as CampaignViewOrderSubscriber: core's template is not touched, a script moves the node on the
 * first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad, so core's scripts have already run
 * on it) and fires a window resize so the chart fits. Moving a node keeps its ids and handlers, so the date range
 * keeps working. The contacts filter above the tabs (`#segment-contact-filters`, the event types select: it filters the
 * contacts, not the chart) is moved to the very end, below the chart, by request; the form keeps its id and handlers.
 * It only acts on a page that has `#contacts-container` and the chart block outside the tabs'
 * content, so no other page is affected, and it does nothing if the chart is already below.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentViewOrderSubscriber implements EventSubscriberInterface
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
            .'if(window.mauticPatchesSegmentViewOrder){return;}'
            .'window.mauticPatchesSegmentViewOrder=true;'
            .'function reorder(){'
            .'var $tabs=mQuery("#contacts-container").closest(".tab-content");'
            .'var $chart=mQuery(".ri-line-chart-fill").first().closest(".pa-md");'
            .'if(!$tabs.length||!$chart.length||$tabs[0].contains($chart[0])){return;}'
            .'var moved=false;'
            // already below the contacts: nothing to do
            .'if(!($chart[0].compareDocumentPosition($tabs[0])&Node.DOCUMENT_POSITION_PRECEDING)){$tabs.after($chart);moved=true;}'
            // the contacts filter (event types) goes to the very end, below the chart
            .'var $form=mQuery("#segment-contact-filters");'
            .'if($form.length&&!($form[0].compareDocumentPosition($chart[0])&Node.DOCUMENT_POSITION_PRECEDING)){$chart.after($form);moved=true;}'
            .'if(moved){window.dispatchEvent(new Event("resize"));}'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;};'
            .'mQuery(reorder);'
            .'})();';
    }
}
