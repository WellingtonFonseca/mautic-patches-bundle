<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A "Filtros" tab, first of the tabs of the segment page (before Contatos and Compartilhamento de campanhas), that
 * shows the filters the segment applies. Until now they could only be seen by opening the edit form.
 *
 * Core's template has no hook for a tab, and is not touched: a script adds the tab (<li>) and its pane to the page's
 * tabs, on the first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad, like the other page
 * scripts), and fills the pane from GET /s/segment-filters/{id} (SegmentFiltersController). The segment's id is read
 * from the contacts pane's data-target-url. The tab is the active one, so the page opens on it (Contatos is one click away; its
 * pane still loads in the background). It acts only on a page that has `#contacts-container`, and once per page.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentFiltersTabSubscriber implements EventSubscriberInterface
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
        $base = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_segment_filters', ['id' => 0]));

        $customContentEvent->addContent('<script>'.self::script($base, $this->translator->trans('mautic.core.filters')).'</script>');
    }

    public static function script(string $filtersBase, string $title): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentFiltersTab){return;}'
            .'window.mauticPatchesSegmentFiltersTab=true;'
            .'function addTab(){'
            .'var $contacts=mQuery("#contacts-container");'
            .'if(!$contacts.length||mQuery("#segment-filters-container").length){return;}'
            .'var m=/\/segment\/view\/(\d+)\/contact/.exec($contacts.attr("data-target-url")||"");'
            .'var $nav=mQuery("a[href=\"#contacts-container\"]").closest("ul");'
            .'if(!m||!$nav.length){return;}'
            // The new tab is the active one: the page opens on it, not on Contatos.
            .'$nav.children("li").removeClass("active");'
            .'$contacts.siblings(".tab-pane").addBack().removeClass("active");'
            .'var $pane=mQuery("<div>",{"class":"tab-pane bdr-w-0 active",id:"segment-filters-container"});'
            .'$contacts.parent().prepend($pane);'
            .'$nav.prepend(mQuery("<li>",{"class":"active"}).append(mQuery("<a>",{href:"#segment-filters-container",role:"tab","data-toggle":"tab"}).text('.json_encode($title, JSON_UNESCAPED_UNICODE).')));'
            .'mQuery.get('.json_encode($filtersBase, JSON_UNESCAPED_SLASHES).'+m[1]).done(function(html){$pane.html(html);});'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addTab();return result;};'
            .'mQuery(addTab);'
            .'})();';
    }
}
