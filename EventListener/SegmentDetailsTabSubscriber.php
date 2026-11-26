<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A "Detalhes" tab on the segment page (like the details modal of the segment list): core's details table (the one behind the "Mais >"
 * / arrow toggle, `#segment-details`) moves into the pane, and the page's right column gets the read-only Configurações + Distinct
 * component (SegmentSettingsColumn, GET /s/segment-settings/{id}) on top. The column shows only while Detalhes is the active tab
 * (the main column takes the whole width on the others). The "Mais >" button of the segment page is gone (ContactViewSidePanelSubscriber skips it).
 *
 * Core's template is not touched: a script, once per page (first load and after every Mautic ajax page load), acts on a page that has `#segment-details`
 * and `#contacts-container`. The tab is first in the tab bar; Filtros (SegmentFiltersTabSubscriber) stays the active one.
 *
 * Injected on 'page.header.left', like the other page scripts.
 */
class SegmentDetailsTabSubscriber implements EventSubscriberInterface
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
        $base = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_segment_settings', ['id' => 0]));

        $customContentEvent->addContent('<script>'.self::script($base, $this->translator->trans('mautic.core.details')).'</script>');
    }

    public static function script(string $settingsBase, string $title): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentDetailsTab){return;}'
            .'window.mauticPatchesSegmentDetailsTab=true;'
            .'function addTab(){'
            .'var $details=mQuery("#segment-details"),$contacts=mQuery("#contacts-container"),$id=mQuery("#entityId");'
            .'var $nav=$contacts.closest(".tab-content").siblings().find("ul.nav-tabs").first();if(!$nav.length){$nav=mQuery("a[href=\"#campaign-container\"]").closest("ul");}'
            .'if(!$details.length||!$contacts.length||!$nav.length||!$id.length||$contacts.closest(".mautic-patches-segment-details").length||$details.data("mauticPatchesDetailsTab")){return;}'
            .'$details.data("mauticPatchesDetailsTab",true);'
            .'var $left=$details.closest(".col-md-9"),$right=$left.siblings(".col-md-3.bdr-l").first();'
            // core\'s collapsible details (and its arrow) move into a pane of their own
            .'var $pane=mQuery("<div>",{"class":"tab-pane bdr-w-0",id:"segment-details-container"}).append($details.find(".panel").first());'
            .'$details.hide();mQuery(".hr-expand",$left).hide();'
            .'$contacts.parent().prepend($pane);'
            .'var $li=mQuery("<li>").append(mQuery("<a>",{href:"#segment-details-container",role:"tab","data-toggle":"tab"}).text('.json_encode($title, JSON_UNESCAPED_UNICODE).'));'
            .'function order(){$nav.prepend($li);}'
            .'order();setTimeout(order,0);setTimeout(order,100);'
            // the read-only column, on top of the right column
            .'var $col=mQuery("<div>");$right.prepend($col);'
            .'mQuery.get('.json_encode($settingsBase, JSON_UNESCAPED_SLASHES).'+$id.val()).done(function(html){$col.html(html);});'
            .'function sync(){'
            .'var on=$pane.hasClass("active");'
            .'$right.toggle(on);$left.css("width",on?"":"100%");'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'$left.on("shown.bs.tab",sync);'
            .'sync();'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addTab();return result;};'
            .'mQuery(addTab);'
            .'})();';
    }
}
