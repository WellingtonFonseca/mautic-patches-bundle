<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the Campaign Builder: the balloon on a connection that tells the wait of an event
 * ("wait 2 days") stays frozen on the canvas after the event is edited. Each time the event form is saved core
 * calls Mautic.campaignBuilderUpdateLabel (campaign.js), which should drop the old balloon and draw the new one.
 * To drop it, it loops over `conn.getOverlays().length`, but the jsPlumb that ships with Mautic (2.15.6) returns
 * an object keyed by overlay id, not an array: `length` is undefined, nothing is removed, and the new balloon (same
 * id) is added next to the old one. The old element is no longer tracked by jsPlumb, so it is never repainted: it
 * stays where it was when the node is dragged.
 *
 * This replaces that function with the same one that removes every Label overlay through Object.keys (works for an
 * object and for an array). It is done on the first load and after every Mautic ajax page load (it wraps
 * Mautic.onPageLoad), because campaign.js may load after this script.
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

    public static function script(): string
    {
        return '(function(){'
            .'if(window.mauticPatchesWaitLabel){return;}'
            .'window.mauticPatchesWaitLabel=true;'
            .'function updateLabel(domEventId){'
            .'var label=typeof Mautic.campaignBuilderLabels[domEventId]=="undefined"?"":Mautic.campaignBuilderLabels[domEventId];'
            .'Mautic.campaignBuilderInstance.select({target:domEventId}).each(function(conn){'
            .'var overlays=conn.getOverlays();'
            .'Object.keys(overlays).forEach(function(key){'
            .'if(overlays[key]&&overlays[key].type=="Label"){conn.removeOverlay(overlays[key].id);}'
            .'});'
            .'if(label){conn.addOverlay(["Label",{label:label,location:0.65,cssClass:"jtk-label",id:conn.sourceId+"_"+conn.targetId+"_connectionLabel"}]);}'
            .'});'
            .'}'
            .'function patch(){if(Mautic.campaignBuilderUpdateLabel&&Mautic.campaignBuilderUpdateLabel!==updateLabel){Mautic.campaignBuilderUpdateLabel=updateLabel;}}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){patch();return original.apply(this,arguments);};'
            .'mQuery(patch);'
            .'})();';
    }
}
