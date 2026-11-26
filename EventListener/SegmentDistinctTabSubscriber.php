<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the "Distinct" block in the right column of the segment edit form (the one with Categoria, Publicado, Global, Ativo): a title
 * and the three rows of SegmentDistinctFormExtension (core's template does not list them, so form_end() renders them at the bottom).
 * The fields are the real ones, with their own errors: only their place changes.
 *
 * It acts on a page that has `#leadlist_distinct_enabled_1` and that column, once, on the first load and after every Mautic ajax page
 * load (it wraps Mautic.onPageLoad, like SegmentPrefixFieldSubscriber). Injected on 'page.header.left'.
 */
class SegmentDistinctTabSubscriber implements EventSubscriberInterface
{
    private const TITLE = 'Distinct';

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
            .'if(window.mauticPatchesSegmentDistinct){return;}'
            .'window.mauticPatchesSegmentDistinct=true;'
            .'function build(){'
            .'var $on=mQuery("#leadlist_distinct_enabled_1"),$side=mQuery(".col-md-3.bdr-l > div").first();'
            .'if(!$on.length||!$side.length||$on.data("moved")){return;}'
            .'$on.data("moved",true);'
            .'var $rows=mQuery("#leadlist_distinct_enabled_1,#leadlist_distinct_remove_empty_1,#leadlist_distinct_fields").closest(".form-group");'
            .'var $box=mQuery("<div class=\"patches-segment-distinct\"><div class=\"form-group col-xs-12\"><h4 class=\"fw-sb\">"+'.json_encode(self::TITLE).'+"</h4></div></div>").appendTo($side);'
            .'$rows.appendTo($box);'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);build();return result;};'
            .'mQuery(build);'
            .'})();';
    }
}
