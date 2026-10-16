<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The wait of a campaign event ("esperar 2 dias") in the Campaign Builder, shown as a strip on top of the node it
 * belongs to, instead of the balloon core draws on the connection line.
 *
 * Why not the balloon: it is a jsPlumb overlay at 65% of the line, so it hides behind other nodes, and core's
 * Mautic.campaignBuilderUpdateLabel (campaign.js) has a bug: to drop the old balloon it loops over
 * `conn.getOverlays().length`, but the jsPlumb that ships with Mautic (2.15.6) returns an object keyed by overlay
 * id, not an array. `length` is undefined, nothing is removed, a second balloon (same id) is drawn and the old one is
 * no longer tracked by jsPlumb, so it stays frozen when the node moves.
 *
 * This replaces that function. It removes every Label overlay of the connections that end at the node (through
 * Object.keys, which works for an object and for an array) and puts the text core already computed
 * (Mautic.campaignBuilderLabels[domEventId]) in a `.campaign-event-wait` strip at the top of the node's
 * `.campaign-event-type`, with the clock icon. No text: the strip is removed. The node grows taller by the strip, so
 * jsPlumb revalidates it to bring the endpoints and lines to the new size. The strip belongs to the node, so it
 * follows it when it is dragged, copied or edited. The function is replaced on the first load and after every Mautic
 * ajax page load (it wraps Mautic.onPageLoad), because campaign.js may load after this script.
 *
 * Injected the same way as CampaignBuilderOverlaySubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignBuilderWaitLabelSubscriber implements EventSubscriberInterface
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

    /** The strip is attached to the card: the card loses its top corners, the strip has them. */
    public const STYLE = '.campaign-event-wait{display:flex;align-items:center;gap:6px;padding:4px 12px;font-size:14px;'
        .'line-height:20px;white-space:nowrap;background:var(--layer-01);color:var(--text-secondary);'
        .'border:2px solid var(--border-strong-01,var(--border-subtle));border-bottom:0;border-radius:8px 8px 0 0}'
        .'.campaign-event-wait+.campaign-event-content{border-top-left-radius:0;border-top-right-radius:0}';

    public static function script(): string
    {
        return '(function(){'
            .'if(window.mauticPatchesWaitLabel){return;}'
            .'window.mauticPatchesWaitLabel=true;'
            .'var style=document.createElement("style");style.textContent='.json_encode(self::STYLE).';document.head.appendChild(style);'
            .'function updateLabel(domEventId){'
            .'var label=typeof Mautic.campaignBuilderLabels[domEventId]=="undefined"?"":Mautic.campaignBuilderLabels[domEventId];'
            // the balloon on the lines that end at the node: gone, whatever jsPlumb returns
            .'Mautic.campaignBuilderInstance.select({target:domEventId}).each(function(conn){'
            .'var overlays=conn.getOverlays();'
            .'Object.keys(overlays).forEach(function(key){'
            .'if(overlays[key]&&overlays[key].type=="Label"){conn.removeOverlay(overlays[key].id);}'
            .'});'
            .'});'
            // the strip on the node
            .'var node=document.getElementById(domEventId);'
            .'var type=node?node.querySelector(".campaign-event-type"):null;'
            .'if(!type){return;}'
            .'var strip=type.querySelector(":scope > .campaign-event-wait");'
            .'if(!label){if(strip){strip.remove();Mautic.campaignBuilderInstance.revalidate(node);}return;}'
            .'if(!strip){strip=document.createElement("div");strip.className="campaign-event-wait";'
            .'strip.innerHTML=\'<i class="ri-time-line"></i><span></span>\';type.insertBefore(strip,type.firstChild);}'
            .'strip.lastChild.textContent=label;'
            .'Mautic.campaignBuilderInstance.revalidate(node);'
            .'}'
            .'function patch(){if(Mautic.campaignBuilderUpdateLabel&&Mautic.campaignBuilderUpdateLabel!==updateLabel){Mautic.campaignBuilderUpdateLabel=updateLabel;}}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){patch();return original.apply(this,arguments);};'
            .'mQuery(patch);'
            .'})();';
    }
}
