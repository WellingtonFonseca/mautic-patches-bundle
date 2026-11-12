<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The script that moves the Estatísticas / Mapa block (`.stats-menu` and `.stats-menu__content`) after another block of a page
 * (campaign page, email page). Moving a node keeps its ids and handlers. It runs on the first load and after every Mautic ajax page
 * load (it wraps Mautic.onPageLoad, so core's scripts have already run on the nodes) and fires a resize for the charts.
 */
final class StatsBlockScript
{
    /**
     * @param string $flag   window property that stops a second install of the script
     * @param string $anchor selector of an element inside the `.tab-content` the statistics block must come after
     * @param string $var    name of the JS variable that holds that `.tab-content`
     */
    public static function after(string $flag, string $anchor, string $var): string
    {
        return '(function(){'
            .'if(window.'.$flag.'){return;}'
            .'window.'.$flag.'=true;'
            .'function reorder(){'
            .'var $'.$var.'=mQuery("'.$anchor.'").closest(".tab-content");'
            .'var $menu=mQuery(".stats-menu");'
            .'var $stats=mQuery(".stats-menu__content");'
            .'if(!$'.$var.'.length||!$menu.length||!$stats.length){return;}'
            // already below the anchor: nothing to do
            .'if($menu[0].compareDocumentPosition($'.$var.'[0])&Node.DOCUMENT_POSITION_PRECEDING){return;}'
            .'$'.$var.'.after($menu,$stats);'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;};'
            .'mQuery(reorder);'
            .'})();';
    }
}
