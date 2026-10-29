<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
 * `.campaign-event-type`, with the hourglass icon (window.mauticPatchesSetWait, also used by the Preview tab of the campaign
 * page, see CampaignPreviewWaitSubscriber). Under the wait text the strip has one line per fact of the schedule (the hours, the
 * days of the week, each with its icon), made by the formatter of CampaignNodeScheduleSubscriber; an event with none has only the
 * wait line. No text: the strip is removed. The node grows taller by the strip, so
 * jsPlumb revalidates it to bring the endpoints and lines to the new size, but only when the strip changed (core calls
 * the function once per connection when the canvas opens, so an unchanged strip costs nothing) and all the nodes of a
 * moment together on the next animation frame (one layout, not one per node). The strip belongs to the node, so it
 * follows it when it is dragged, copied or edited. The function is replaced on the first load and after every Mautic
 * ajax page load (it wraps Mautic.onPageLoad), because campaign.js may load after this script.
 *
 * Injected the same way as CampaignBuilderOverlaySubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignBuilderWaitLabelSubscriber implements EventSubscriberInterface
{
    public function __construct(private ?TranslatorInterface $translator = null)
    {
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

        $customContentEvent->addContent('<script>'.self::script($this->rewrites()).'</script>');
    }

    /** The strip is attached to the card: the card loses its top corners, the strip has them. */
    public const STYLE = '.campaign-event-wait{display:flex;flex-direction:column;gap:2px;padding:4px 12px;font-size:14px;'
        .'line-height:20px;white-space:nowrap;background:var(--layer-01);color:var(--text-secondary);'
        .'border:2px solid var(--border-strong-01,var(--border-subtle));border-bottom:0;border-radius:8px 8px 0 0}'
        .'.campaign-event-wait+.campaign-event-content{border-top-left-radius:0;border-top-right-radius:0}'
        // one line per fact: the wait, then the hours and the days of the schedule (CampaignNodeScheduleSubscriber)
        .'.campaign-event-wait-line{display:flex;align-items:center;gap:6px}'
        .'.campaign-event-wait-extra{display:flex;flex-direction:column;gap:2px}';

    /**
     * Core's text for an event on the no path ("entre 1 dia") is a deadline that only makes sense after a decision. After
     * a condition the event simply waits, so the script turns the inaction text back into the plain one. Both texts come
     * from the translator with their placeholders (%number%, %unit%, %full%...), so any language works.
     *
     * @return list<array{from: string, to: string}>
     */
    public function rewrites(): array
    {
        if (null === $this->translator) {
            return [];
        }

        $rewrites = [];
        foreach (['interval', 'date'] as $mode) {
            $key = 'mautic.campaign.connection.trigger.'.$mode.'.label';
            $rewrites[] = ['from' => $this->translator->trans($key.'_inaction'), 'to' => $this->translator->trans($key)];
        }

        return $rewrites;
    }

    /** @param list<array{from: string, to: string}> $rewrites */
    public static function script(array $rewrites = []): string
    {
        return '(function(){'
            .'if(window.mauticPatchesWaitLabel){return;}'
            .'window.mauticPatchesWaitLabel=true;'
            .'var style=document.createElement("style");style.textContent='.json_encode(self::STYLE).';document.head.appendChild(style);'
            // jsPlumb brings the lines to the new height of the node. Only when the strip really changed, and all the nodes
            // of a moment in one go on the next frame: revalidating each one right after its strip is written makes the
            // browser lay the page out again every time (a campaign of 150 waits opened 50% slower).
            .'var pending=[],queued=false;'
            .'function revalidate(node){'
            .'if(!Mautic.campaignBuilderInstance){return;}'
            .'if(pending.indexOf(node)<0){pending.push(node);}'
            .'if(queued){return;}'
            .'queued=true;'
            .'requestAnimationFrame(function(){'
            .'queued=false;var list=pending;pending=[];var i=Mautic.campaignBuilderInstance;'
            .'if(!i){return;}'
            .'list.forEach(function(n){try{i.revalidate(n);}catch(e){}});'
            .'});'
            .'}'
            // the lines of the schedule (hours, days) under the wait text, in one block: rebuilt only when they changed; true if so
            .'function setLines(strip,lines){'
            .'var extra=strip.querySelector(":scope > .campaign-event-wait-extra");'
            .'var sig=lines.map(function(l){return l.icon+"|"+l.text;}).join("\\n");'
            .'if(!lines.length){if(extra){extra.remove();return true;}return false;}'
            .'if(extra&&extra.getAttribute("data-lines")===sig){return false;}'
            .'if(extra){extra.remove();}'
            .'extra=document.createElement("div");extra.className="campaign-event-wait-extra";extra.setAttribute("data-lines",sig);'
            .'lines.forEach(function(l){'
            .'var row=document.createElement("div");row.className="campaign-event-wait-line";'
            .'var icon=document.createElement("i");icon.className=l.icon;'
            .'var text=document.createElement("span");text.textContent=l.text;'
            .'row.appendChild(icon);row.appendChild(text);extra.appendChild(row);'
            .'});'
            .'strip.appendChild(extra);return true;'
            .'}'
            // what the formatter (CampaignNodeScheduleSubscriber) makes of the fields of an event; no lines without it
            .'function scheduleOf(event){var api=window.mauticPatchesScheduleApi;return api?api.format(event):[];}'
            // core's text of a wait until a date, with the date in the language and time zone of the user
            .'function dated(label,event){var api=window.mauticPatchesScheduleApi;return api?api.localizeDate(label,event):label;}'
            // the strip on a node: created, rewritten or removed; the preview of the campaign page uses it too
            .'window.mauticPatchesSetWait=function(node,label,lines){'
            .'var type=node?node.querySelector(".campaign-event-type"):null;'
            .'if(!type){return;}'
            .'var strip=type.querySelector(":scope > .campaign-event-wait");'
            .'if(!label){if(strip){strip.remove();revalidate(node);}return;}'
            .'var changed=false;'
            .'if(!strip){strip=document.createElement("div");strip.className="campaign-event-wait";'
            .'strip.innerHTML=\'<div class="campaign-event-wait-line"><i class="ri-hourglass-line"></i><span class="campaign-event-wait-text"></span></div>\';'
            .'type.insertBefore(strip,type.firstChild);changed=true;}'
            .'var text=strip.querySelector(".campaign-event-wait-text");'
            .'if(text.textContent!==label){text.textContent=label;changed=true;}'
            .'if(setLines(strip,lines||[])){changed=true;}'
            .'if(changed){revalidate(node);}'
            .'};'
            // the Preview tab of the campaign page (CampaignPreviewWaitSubscriber) leaves its texts in
            // window.mauticPatchesPreviewWaits; they are put on the nodes now and after every Mautic page load, because the
            // content that comes over ajax is not in the page yet when its own inline script runs
            .'window.mauticPatchesApplyWaits=function(){'
            .'var waits=window.mauticPatchesPreviewWaits||{};'
            .'var schedules=window.mauticPatchesPreviewSchedules||{};'
            .'Object.keys(waits).forEach(function(id){'
            .'window.mauticPatchesSetWait(document.querySelector("#preview-container #"+id),dated(waits[id],schedules[id]),scheduleOf(schedules[id]));'
            .'});'
            .'};'
            // after a condition the no path waits like any event: the inaction text is turned back into the plain one
            .'var rewrites='.json_encode($rewrites, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).';'
            .'function plain(label){'
            .'for(var i=0;i<rewrites.length;i++){'
            .'var from=rewrites[i].from,parts=from.split(/%\w+%/),names=(from.match(/%\w+%/g)||[]).map(function(n){return n.slice(1,-1);});'
            .'if(!names.length||label.indexOf(parts[0])!==0){continue;}'
            .'var pos=parts[0].length,vals=[],ok=true;'
            .'for(var k=0;k<names.length&&ok;k++){'
            .'var tail=parts[k+1],end=(k===names.length-1)?(tail===""?label.length:label.lastIndexOf(tail)):label.indexOf(tail,pos);'
            .'if(end<pos){ok=false;break;}'
            .'vals.push(label.slice(pos,end));pos=end+tail.length;'
            .'}'
            .'if(!ok||pos!==label.length){continue;}'
            .'return rewrites[i].to.replace(/%(\w+)%/g,function(x,n){var at=names.indexOf(n);return at<0?x:vals[at];});'
            .'}'
            .'return label;'
            .'}'
            .'function fromCondition(conns){'
            .'var yes=false;'
            .'conns.each(function(c){var s=document.getElementById(c.sourceId);if(s&&s.getAttribute("data-type")=="condition"){yes=true;}});'
            .'return yes;'
            .'}'
            .'function updateLabel(domEventId){'
            .'var label=typeof Mautic.campaignBuilderLabels[domEventId]=="undefined"?"":Mautic.campaignBuilderLabels[domEventId];'
            .'if(label&&fromCondition(Mautic.campaignBuilderInstance.select({target:domEventId}))){label=plain(label);}'
            // the balloon on the lines that end at the node: gone, whatever jsPlumb returns
            .'Mautic.campaignBuilderInstance.select({target:domEventId}).each(function(conn){'
            .'var overlays=conn.getOverlays();'
            .'Object.keys(overlays).forEach(function(key){'
            .'if(overlays[key]&&overlays[key].type=="Label"){conn.removeOverlay(overlays[key].id);}'
            .'});'
            .'});'
            .'var events=Mautic.campaignBuilderCanvasEvents||{};'
            .'var event=events[domEventId.replace("CampaignEvent_","")];'
            .'window.mauticPatchesSetWait(document.getElementById(domEventId),dated(label,event),scheduleOf(event));'
            .'}'
            // core calls the label update BEFORE it stores the edited event in Mautic.campaignBuilderCanvasEvents, so after an edit the
            // schedule is read again once core is done
            .'function patchEdit(){'
            .'var original=Mautic.campaignEventOnLoad;'
            .'if(!original||original.mauticPatchesSchedule){return;}'
            .'var wrapped=function(container,response){'
            .'var result=original.apply(this,arguments);'
            .'var id=response&&response.eventId&&!response.deleted?"CampaignEvent_"+response.eventId:null;'
            .'if(id&&Mautic.campaignBuilderInstance&&document.getElementById(id)){updateLabel(id);}'
            .'return result;'
            .'};'
            .'wrapped.mauticPatchesSchedule=true;Mautic.campaignEventOnLoad=wrapped;'
            .'}'
            .'function patch(){patchEdit();if(Mautic.campaignBuilderUpdateLabel&&Mautic.campaignBuilderUpdateLabel!==updateLabel){Mautic.campaignBuilderUpdateLabel=updateLabel;}}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){patch();var result=original.apply(this,arguments);window.mauticPatchesApplyWaits();return result;};'
            .'mQuery(patch);'
            .'})();';
    }
}
