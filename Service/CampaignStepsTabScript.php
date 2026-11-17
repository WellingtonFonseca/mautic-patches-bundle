<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The script of the "Etapas" tab of the campaign page (steps_tab_pane.html.twig inlines this constant): a click on a failed or waiting badge fills
 * the title of the table (the badge of the state and the step name) and loads the contacts; the load shows core's bar under the header (showLoadingBar on the request)
 * and dims the table, until the answer of the LAST click. Also Mautic.patchesStepsLoad, the loader of the "Recalculate dates" box.
 *
 * Kept in this class, not inline in the pane, so that Tests/js/campaign-steps-tab.test.js runs the real script.
 */
class CampaignStepsTabScript
{
    public const SCRIPT = '(function(){'
        .'if(window.mauticPatchesStepsTab){return;}'
        .'window.mauticPatchesStepsTab=true;'
        // also the loader of the "Recalculate dates" box, which reloads the list after Apply
        .'Mautic.patchesStepsLoad=load;'
        // two quick clicks: only the last list may fill the table, whichever answer arrives last, and only that one undoes the dimming
        .'var latest=0;'
        .'function table(){return document.getElementById("patches-steps-table");}'
        .'function load(url){'
        .'var mine=++latest,box=table();'
        // the old list is dimmed, not emptied, so the page does not jump. showLoadingBar: core starts the bar under the header before the request and stops
        // it when it ends (its ajaxComplete), for every request, so a late first answer cannot hide the bar of the second one.
        .'box.classList.add("patches-steps-table--loading");'
        .'mQuery.ajax({url:url,type:"GET",showLoadingBar:true}).done(function(response){'
        .'if(mine!==latest){return;}'
        .'response.target="#patches-steps-table";'
        .'Mautic.processPageContent(response);'
        .'}).fail(function(request,status,error){'
        // a 403 / 404 / expired session: core's own error handling
        .'Mautic.processAjaxError(request,status,error);'
        .'}).always(function(){'
        .'if(mine!==latest){return;}'
        .'box.classList.remove("patches-steps-table--loading");'
        .'});'
        .'}'
        // The title of the table: the badge of the clicked state (its color and icon, the word instead of the %) and the name of the step. textContent only: the name is typed by users.
        .'function setHeading(button){'
        .'var heading=document.getElementById("patches-steps-heading"),row=button.closest("[data-event-id]");'
        .'if(!heading||!row){return;}'
        .'var badge=document.createElement("span"),icon=document.createElement("i"),word=document.createElement("span"),title=document.createElement("h4"),source=button.querySelector("i");'
        .'badge.className=button.className.split(" ").filter(function(name){return 0===name.indexOf("patches-badge--");}).concat(["patches-badge","patches-steps-heading-badge"]).join(" ");'
        .'icon.className=source?source.className:"";'
        .'icon.setAttribute("aria-hidden","true");'
        .'word.textContent=button.getAttribute("data-heading")||"";'
        .'title.className="patches-steps-heading-title";'
        .'title.textContent=row.getAttribute("data-step-name")||"";'
        .'badge.appendChild(icon);'
        .'badge.appendChild(word);'
        .'heading.textContent="";'
        .'heading.appendChild(badge);'
        .'heading.appendChild(title);'
        .'heading.hidden=false;'
        .'}'
        // data-loaded: a later show of the tab must not replace the list a click loaded
        .'mQuery(document).on("shown.bs.tab","a[href=\"#patches-steps-container\"]",function(){'
        .'var box=table();'
        .'if(box&&!box.hasAttribute("data-loaded")){'
        .'box.setAttribute("data-loaded","1");'
        .'load(box.getAttribute("data-target-url"));'
        .'}'
        .'});'
        .'document.addEventListener("click",function(event){'
        .'var button=event.target&&event.target.closest?event.target.closest("#patches-steps-container button.patches-steps-badge"):null;'
        .'if(!button){return;}'
        .'document.querySelectorAll("#patches-steps-container button.patches-steps-badge.active").forEach(function(other){other.classList.remove("active");});'
        .'button.classList.add("active");'
        .'setHeading(button);'
        .'table().setAttribute("data-loaded","1");'
        .'load(button.getAttribute("data-target-url"));'
        .'});'
        .'})();';
}
