<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The script of the "Recalculate dates" box of the "Etapas" tab of the campaign page (its markup is in steps_tab_pane.html.twig,
 * which inlines this constant only when a Wait step of the campaign can be recalculated). The box shows above the contacts table only while
 * the table holds the waiting list (data-list="waiting": the scheduled and the waiting badges) of a Wait step the user can edit: that row
 * carries the simulate and apply urls (CampaignStepsTabSubscriber). Any other badge hides it.
 *
 * Kept in this class, not inline in the pane, so that Tests/js/campaign-wait-recalculate.test.js runs the real script.
 */
class CampaignWaitRecalculateScript
{
    public const SCRIPT = '(function(){'
        .'if(window.mauticPatchesWaitRecalc){return;}'
        .'window.mauticPatchesWaitRecalc=true;'
        // Every badge click starts a new run. An answer of an older run belongs to a list no longer on screen: it never touches the box or
        // the table (Apply would post for a step whose simulation the user did not see), but its error and its result are still told.
        .'var run=0;'
        // Bubble phase, like the pane's own handler that loads the table: both act on the same click.
        .'document.addEventListener("click",function(event){'
        .'var button=event.target&&event.target.closest?event.target.closest("#patches-steps-container button.patches-steps-badge"):null;'
        .'var box=button?document.getElementById("patches-wait-recalc"):null;'
        .'if(!box){return;}'
        .'run++;'
        .'var row=button.closest("[data-recalc-simulate-url]");'
        // the waiting list is the only one the recalculation changes
        .'if(!row||"waiting"!==button.getAttribute("data-list")){box.hidden=true;return;}'
        .'setUp(box,row,button,run);'
        .'});'
        // The message on top and one row of buttons below it (the loader line "<spinner> Recalculating..." takes the message's place
        // while it runs). Recalculate shows what the current rule would do (nothing is written); when something would change, it gives
        // way to Apply and Cancel. Apply writes it and reloads the table. The texts are in the box's data attributes ("%count%" is
        // replaced). The handlers are set again on each click, so they always point at the row of the list on screen.
        .'function setUp(box,row,button,mine){'
        .'function current(){return mine===run;}'
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
        .'mQuery.getJSON(row.getAttribute("data-recalc-simulate-url"),function(data){if(current()){show(data);}})'
        .'.fail(function(request,status,error){if(current()){idle();}Mautic.processAjaxError(request,status,error);});'
        .'};'
        .'cancel.onclick=function(){out.hidden=true;idle();};'
        .'apply.onclick=function(){'
        .'apply.disabled=true;'
        .'mQuery.ajax({type:"POST",url:row.getAttribute("data-recalc-apply-url"),dataType:"json"})'
        .'.done(function(data){'
        // the dates were written even when another list is on screen now: a notification says so
        .'if(!current()){Mautic.setFlashes(Mautic.addFlashMessage(text("applied",data.applied)));return;}'
        .'idle();out.innerHTML="";out.hidden=false;line(text("applied",data.applied)).className="patches-wait-recalc-applied";'
        // the list on screen shows the new dates (Mautic.patchesStepsLoad is the pane's loader of the table)
        .'Mautic.patchesStepsLoad(button.getAttribute("data-target-url"));})'
        .'.fail(function(request,status,error){if(current()){apply.disabled=false;}Mautic.processAjaxError(request,status,error);});'
        .'};'
        .'}'
        .'})();';
}
