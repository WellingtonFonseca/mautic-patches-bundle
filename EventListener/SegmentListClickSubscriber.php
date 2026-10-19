<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A click on a segment's NAME in the segment list opens, in core's shared modal (#MauticSharedModal, Mautic.ajaxifyModal),
 * the tabs of the segment page (Details, Filters, Contacts, Campaign share; the body is rendered by
 * SegmentDetailsController) instead of leaving the list. The segment's page stays one click away: the "Details" option of
 * the row's three-dots menu (SegmentDetailsButtonSubscriber) and the Details button of the modal. A modified click
 * (ctrl/cmd/shift), the middle button and the menu's own links are left to the browser and to core.
 *
 * Same technique as ContactListClickSubscriber: a click handler in the capture phase (before core's ajax link handler, which
 * it then stops), only for links to /s/segments/view/{id} inside the segment list (#page-list-wrapper on /s/segments).
 *
 * The Contacts tab and the campaign shares are not in the modal body: when their tab is first shown, the script loads
 * them the way core's segment page does (the contacts through core's own loader of #contacts-container, the shares
 * through the lead:getCampaignShareStats action), so the modal opens at once.
 */
class SegmentListClickSubscriber implements EventSubscriberInterface
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

        // The route with a placeholder id of 0: the script puts the segment's id after the base.
        $base = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_segment_details', ['id' => 0]));

        $customContentEvent->addContent('<script>'.self::script($base, $this->translator->trans('mautic.patches.segment.details.title')).'</script>');
    }

    public static function script(string $detailsBase, string $header): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentListClicks){return;}'
            .'window.mauticPatchesSegmentListClicks=true;'
            .'var re=/^\/s\/segments\/view\/(\d+)(?:[?#].*)?$/;'
            .'var listRe=/^\/s\/segments(?:\/\d+)?$/;'
            // Capture phase: runs before core's ajax link handler, so the click never reaches it.
            .'document.addEventListener("click",function(event){'
            .'var a=event.target.closest?event.target.closest("#page-list-wrapper a[href]"):null;'
            .'if(!a||event.button||event.ctrlKey||event.metaKey||event.shiftKey){return;}'
            .'if(!listRe.test(window.location.pathname)||a.closest(".page-list-actions")){return;}'
            .'var m=re.exec(a.getAttribute("href")||"");if(!m){return;}'
            .'event.preventDefault();event.stopImmediatePropagation();'
            .'var el=document.createElement("a");'
            .'el.setAttribute("href",'.json_encode($detailsBase, JSON_UNESCAPED_SLASHES).'+m[1]);'
            .'el.setAttribute("data-toggle","ajaxmodal");'
            .'el.setAttribute("data-target","#MauticSharedModal");'
            .'el.setAttribute("data-header",'.json_encode($header, JSON_UNESCAPED_UNICODE).');'
            .'Mautic.ajaxifyModal(el,event);'
            .'},true);'
            // The slow tabs, once each per opened modal (the body is new every time, so the mark lives on the tab link).
            .'mQuery(document).on("shown.bs.tab",".mautic-patches-segment-details a[data-patches-load]",function(){'
            .'var $a=mQuery(this);if($a.data("patchesLoaded")){return;}$a.data("patchesLoaded",true);'
            .'var id=$a.closest(".mautic-patches-segment-details").data("segment-id");'
            .'if($a.data("patches-load")==="contacts"){'
            .'var $c=mQuery("#contacts-container"),url=$c.data("target-url");'
            .'if(url){mQuery.get(url,function(response){response.target="#contacts-container";Mautic.processPageContent(response);});}'
            .'return;}'
            .'var ids=[];mQuery(".patches-campaign-share-stat").each(function(){if(!mQuery(this).text()){ids.push(mQuery(this).attr("data-value"));}});'
            .'while(ids.length>0){'
            .'Mautic.ajaxActionRequest("lead:getCampaignShareStats",{ids:ids.splice(0,10),entityId:id},function(response){'
            .'if(response.success&&response.stats){response.stats.forEach(function(stat){mQuery("#patches-campaign-share-stat-"+stat.id).html(stat.data);});}'
            .'},false,true,"GET");}'
            .'});'
            .'})();';
    }
}
