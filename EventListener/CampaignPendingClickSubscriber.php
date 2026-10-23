<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A click on the hourglass (the pending count) of a campaign node (only the Preview tab of the campaign page has the counts, the
 * builder shows none) opens, in core's shared modal (#MauticSharedModal, Mautic.ajaxifyModal), the contacts waiting at that
 * node (the body is rendered by CampaignEventPendingController, the table by CampaignPendingContactsController, loaded by
 * Mautic.campaignPendingOnLoad). The check next to it (the done count) opens nothing ON PURPOSE: a list of who finished the node
 * would mix people who did it at another moment (an earlier run, a restart) with who is waiting now, and it reads as a status.
 *
 * Core's template (Event/_preview.html.twig, the node) is not touched: a click handler in the capture phase, before core's own
 * handlers, looks at the stat span that holds `.ri-hourglass-line`; the node's id
 * is the data-event-id of the node it sits in. Same technique as ContactListClickSubscriber and injected the same way
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left'). The hourglass gets the pointer cursor from a style.
 */
class CampaignPendingClickSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
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

        // The route with a placeholder id of 0: the script puts the event's id after the base.
        $pending = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_campaign_event_pending', ['eventId' => 0]));

        $customContentEvent->addContent(
            '<style>.campaign-event-stat > span[data-toggle]:has(.ri-hourglass-line){cursor:pointer}</style>'
            .'<script>'.self::script($pending, $this->translator->trans('mautic.patches.campaign.pending.title')).'</script>'
        );
    }

    public static function script(string $pendingBase, string $pendingHeader): string
    {
        return '(function(){'
            .'if(window.mauticPatchesCampaignPendingClicks){return;}'
            .'window.mauticPatchesCampaignPendingClicks=true;'
            // Capture phase: runs before core's handlers, so the click never reaches them.
            .'document.addEventListener("click",function(event){'
            .'var span=event.target&&event.target.closest?event.target.closest(".campaign-event-stat > span[data-toggle]"):null;'
            // only the hourglass: the check (done count) opens nothing
            .'if(!span||event.button||!span.querySelector(".ri-hourglass-line")){return;}'
            .'var node=span.closest("[data-event-id]"),id=node?node.getAttribute("data-event-id"):null;'
            .'if(!id){return;}'
            .'event.preventDefault();event.stopImmediatePropagation();'
            .'var el=document.createElement("a");'
            .'el.setAttribute("href",'.json_encode($pendingBase, JSON_UNESCAPED_SLASHES).'+id);'
            .'el.setAttribute("data-toggle","ajaxmodal");'
            .'el.setAttribute("data-target","#MauticSharedModal");'
            .'el.setAttribute("data-header",'.json_encode($pendingHeader, JSON_UNESCAPED_UNICODE).');'
            .'Mautic.ajaxifyModal(el,event);'
            .'},true);'
            // The modal's body only has the container: its table is loaded when the body arrives (core calls <mauticContent>OnLoad).
            .'Mautic.campaignPendingLoadTable=function(){'
            .'var url=mQuery("#patches-pending-container").data("target-url");'
            .'if(url){mQuery.get(url,function(response){response.target="#patches-pending-container";Mautic.processPageContent(response);})'
            // a 403 / 404 / expired session: core's own error handling, not a spinner that never goes away
            .'.fail(function(request,status,error){Mautic.processAjaxError(request,status,error);});}'
            .'};'
            .'Mautic.campaignPendingOnLoad=function(){Mautic.campaignPendingLoadTable();Mautic.campaignPendingRecalc();};'
            // The "Recalculate dates" box of a Wait node (its markup is in pending_modal.html.twig): the message on top and one row of
            // buttons below it (the loader line "<spinner> Recalculating..." takes the message's place while it runs). Recalculate shows what the current rule would do (nothing is written); when something would change, it
            // gives way to Apply and Cancel. Apply writes it and reloads the table. The texts are in the box's data attributes ("%count%" is replaced).
            .'Mautic.campaignPendingRecalc=function(){'
            .'var box=document.getElementById("patches-wait-recalc");'
            .'if(!box){return;}'
            .'var out=box.querySelector(".patches-wait-recalc-result"),running=box.querySelector(".patches-wait-recalc-running"),start=box.querySelector("[data-recalc=start]"),'
            .'apply=box.querySelector("[data-recalc=apply]"),cancel=box.querySelector("[data-recalc=cancel]");'
            .'function text(key,count){return box.getAttribute("data-text-"+key).replace("%count%",count);}'
            .'function line(value){var p=document.createElement("p");p.textContent=value;out.appendChild(p);return p;}'
            // Recalculate alone: no request running, nothing to apply
            .'function idle(){running.hidden=true;start.hidden=false;start.disabled=false;apply.hidden=true;apply.disabled=false;cancel.hidden=true;}'
            .'function show(data){'
            .'idle();out.innerHTML="";out.hidden=false;'
            .'var summary=data.summary,list=document.createElement("ul"),any=summary.changed+summary.leaving_now>0;'
            .'["changed","leaving_now","unchanged"].forEach(function(key){if(summary[key]){var li=document.createElement("li");li.textContent=text(key,summary[key]);list.appendChild(li);}});'
            .'line(any?text("intro",""):text("nothing",""));'
            .'if(any){out.appendChild(list);}'
            // only Apply and Cancel while there is something to apply
            .'if(data.canApply){start.hidden=true;apply.hidden=false;cancel.hidden=false;}'
            .'}'
            .'start.onclick=function(){'
            // the message line is "<spinner> Recalculating..." while the simulation runs
            .'start.disabled=true;out.hidden=true;running.hidden=false;'
            .'mQuery.getJSON(box.getAttribute("data-simulate-url"),show)'
            .'.fail(function(request,status,error){idle();Mautic.processAjaxError(request,status,error);});'
            .'};'
            .'cancel.onclick=function(){out.hidden=true;idle();};'
            .'apply.onclick=function(){'
            .'apply.disabled=true;'
            .'mQuery.ajax({type:"POST",url:box.getAttribute("data-apply-url"),dataType:"json"})'
            .'.done(function(data){idle();out.innerHTML="";out.hidden=false;line(text("applied",data.applied)).className="patches-wait-recalc-applied";Mautic.campaignPendingLoadTable();})'
            .'.fail(function(request,status,error){apply.disabled=false;Mautic.processAjaxError(request,status,error);});'
            .'};'
            .'};'
            .'})();';
    }
}
