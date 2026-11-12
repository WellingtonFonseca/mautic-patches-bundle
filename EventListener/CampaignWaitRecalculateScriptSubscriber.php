<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The script of the "Recalculate dates" box of the second "Ações" tab of the campaign page (its markup is in actions_tab_pane.html.twig).
 * The box shows above the contacts table only while the table holds the waiting list (clock or hourglass button) of a Wait step the user
 * can edit: that row carries the simulate and apply urls (CampaignActionsTabSubscriber). Any other button hides it.
 *
 * Injected on 'page.header.left' (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT) and kept in this class, not inline in the pane, so that
 * Tests/js/campaign-wait-recalculate.test.js runs the real script.
 */
class CampaignWaitRecalculateScriptSubscriber implements EventSubscriberInterface
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
            .'if(window.mauticPatchesWaitRecalc){return;}'
            .'window.mauticPatchesWaitRecalc=true;'
            // Bubble phase, like the pane's own handler that loads the table: both act on the same click.
            .'document.addEventListener("click",function(event){'
            .'var button=event.target&&event.target.closest?event.target.closest("#patches-actions-container button.patches-actions-badge"):null;'
            .'var box=button?document.getElementById("patches-wait-recalc"):null;'
            .'if(!box){return;}'
            .'var row=button.closest("[data-recalc-simulate-url]");'
            // the scheduled and the waiting buttons both load the waiting list: the only one the recalculation changes
            .'if(!row||["scheduled","waiting"].indexOf(button.getAttribute("data-state"))<0){box.hidden=true;return;}'
            .'setUp(box,row,button);'
            .'});'
            // The message on top and one row of buttons below it (the loader line "<spinner> Recalculating..." takes the message's place
            // while it runs). Recalculate shows what the current rule would do (nothing is written); when something would change, it gives
            // way to Apply and Cancel. Apply writes it and reloads the table. The texts are in the box's data attributes ("%count%" is
            // replaced). The handlers are set again on each click, so they always point at the row of the list on screen.
            .'function setUp(box,row,button){'
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
            .'box.hidden=false;out.innerHTML="";out.hidden=true;idle();'
            .'start.onclick=function(){'
            // the message line is "<spinner> Recalculating..." while the simulation runs
            .'start.disabled=true;out.hidden=true;running.hidden=false;'
            .'mQuery.getJSON(row.getAttribute("data-recalc-simulate-url"),show)'
            .'.fail(function(request,status,error){idle();Mautic.processAjaxError(request,status,error);});'
            .'};'
            .'cancel.onclick=function(){out.hidden=true;idle();};'
            .'apply.onclick=function(){'
            .'apply.disabled=true;'
            .'mQuery.ajax({type:"POST",url:row.getAttribute("data-recalc-apply-url"),dataType:"json"})'
            .'.done(function(data){idle();out.innerHTML="";out.hidden=false;line(text("applied",data.applied)).className="patches-wait-recalc-applied";'
            // the list on screen shows the new dates (Mautic.patchesActionsLoad is the pane's loader of the table)
            .'Mautic.patchesActionsLoad(button.getAttribute("data-target-url"));})'
            .'.fail(function(request,status,error){apply.disabled=false;Mautic.processAjaxError(request,status,error);});'
            .'};'
            .'}'
            .'})();';
    }
}
