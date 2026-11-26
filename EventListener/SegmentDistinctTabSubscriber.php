<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the "DISTINCT" tab to the segment edit form, right after "Filters", and moves into it the three rows of
 * SegmentDistinctFormExtension (core's template does not list them, so form_end() renders them at the bottom).
 * The fields are the real ones, with their own errors: only their place changes. The tab label turns red (text-danger)
 * when a row inside has an error.
 *
 * It acts on a page that has `#leadlist_distinct_enabled_1` and a `ul.nav-tabs` with `a[href="#filters"]`, once, on the
 * first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad, like SegmentPrefixFieldSubscriber).
 * Injected on 'page.header.left'.
 */
class SegmentDistinctTabSubscriber implements EventSubscriberInterface
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
            .'if(window.mauticPatchesSegmentDistinct){return;}'
            .'window.mauticPatchesSegmentDistinct=true;'
            .'function build(){'
            .'var $on=mQuery("#leadlist_distinct_enabled_1"),$link=mQuery("ul.nav-tabs a[href=\"#filters\"]"),$pane=mQuery("#filters.tab-pane");'
            .'if(!$on.length||!$link.length||!$pane.length||$on.data("moved")||mQuery("#distinct").length){return;}'
            .'$on.data("moved",true);'
            .'var $rows=mQuery("#leadlist_distinct_enabled_1,#leadlist_distinct_remove_empty_1,#leadlist_distinct_fields").closest(".form-group");'
            .'var $tab=mQuery("<li><a href=\"#distinct\" data-toggle=\"tab\">DISTINCT</a></li>").insertAfter($link.closest("li"));'
            .'var $new=mQuery("<div class=\"tab-pane fade bdr-w-0\" id=\"distinct\"><div class=\"pa-md\"></div></div>").insertAfter($pane);'
            .'$rows.appendTo($new.children());'
            .'if($rows.filter(".has-error").length){$tab.find("a").addClass("text-danger");}'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);build();return result;};'
            .'mQuery(build);'
            .'})();';
    }
}
