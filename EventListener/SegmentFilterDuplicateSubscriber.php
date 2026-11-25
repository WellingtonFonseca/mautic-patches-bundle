<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * "Duplicar condição" on every filter of the segment form: a copy of that filter (same field, operator, value and AND / OR)
 * right below it, to write the same condition for another value without searching the field again. Core's copy button
 * copies a whole OR group to the end of the list and is hidden on an AND filter. The behaviour is in
 * Resources/js/segment-filter-duplicate.js; the script only acts where the filters list (#leadlist_filters) exists.
 *
 * Injected the same way as SegmentInChipsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentFilterDuplicateSubscriber implements EventSubscriberInterface
{
    // the three action buttons of core are hidden; their icon badges sit to the right of the value input and push it (flex, no overlap); hover opens the word to the right
    public const STYLE = '#leadlist_filters .segment-filter .segment-button{display:none}'
        .'#leadlist_filters .segment-filter>.panel-heading{display:none}'
        // one row on the 12 slots: E/OU 1, field name 4, condition 2, value with the badges 5 (each box keeps an 8px gap inside its slots)
        .'#leadlist_filters .segment-filter>.panel-body{display:flex;align-items:center}'
        .'#leadlist_filters .segment-filter>.panel-body::before,#leadlist_filters .segment-filter>.panel-body::after{display:none}'
        .'#leadlist_filters .segment-filter>.panel-body>div{float:none;box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue{flex:0 0 calc(8.3333% - 8px);width:auto;padding:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue.hide{display:block!important;visibility:hidden}'
        .'#leadlist_filters .segment-filter>.panel-body>.field-name{flex:0 0 calc(33.3333% - 8px);padding:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-3.padding-none{flex:0 0 calc(16.6667% - 8px)}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-6{flex:0 0 calc(41.6667% - 8px)}'
        // the floating bar at the bottom centre: the same 12 slots (1 + 4 + 2 + 5) as a filter row; only layout
        .'.patches-filter-dock{position:fixed;left:50%;bottom:56px;transform:translateX(-50%);z-index:1000;box-sizing:border-box;width:min(1430px,92vw);margin:0;border:1px solid var(--border-subtle,#e0e0e0);border-radius:12px;box-shadow:none!important}'
        .'.patches-filter-dock.patches-dock-off{display:none!important}'
        .'.patches-filter-dock:hover{box-shadow:none!important;transform:translateX(-50%)}'
        .'.patches-filter-dock,.patches-filter-dock:hover{background:#2C355A!important}'
        .'.patches-filter-dock>.panel-body,.patches-filter-dock:hover>.panel-body{background:transparent!important;border-radius:inherit}'
        .'.patches-filter-dock>.panel-body{display:flex;align-items:center;padding:12px 8px 12px 16px}'
        .'.patches-filter-dock>.panel-body>div{box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'.patches-filter-dock__glue{flex:0 0 calc(8.3333% - 8px)}.patches-filter-dock__field{flex:0 0 calc(33.3333% - 8px)}'
        .'.patches-filter-dock__condition{flex:0 0 calc(16.6667% - 8px)}.patches-filter-dock__value{flex:0 0 calc(41.6667% - 8px);display:flex;align-items:center;gap:8px}'
        .'.patches-filter-dock__value>.col-sm-6{flex:1 1 auto;min-width:0}'
        .'.patches-filter-dock__value>.col-sm-6{float:none;width:auto;padding:0}'
        .'.patches-filter-dock__condition>.col-sm-3{float:none;width:100%;padding:0}'
        .'.patches-filter-dock__glue>.panel-glue{float:none;width:100%;padding:0;margin:0}'
        .'.patches-filter-dock__glue>.panel-glue.hide{display:block!important;visibility:hidden}'
        .'.patches-filter-dock__draft{display:none}'
        // the alerts of the value widget: floating above the bar, same width, with a gap (the copy in the widget is hidden)
        .'.patches-filter-dock__value .alert{display:none}'
        .'.patches-filter-dock__alerts{position:absolute;left:0;right:0;bottom:100%;margin-bottom:12px;display:flex;flex-direction:column;gap:8px}'
        .'.patches-filter-dock__alerts:empty{display:none}'
        .'.patches-filter-dock__alerts .alert{margin:0}'
        // the widgets core loads for the value and the condition bring row / form-group margins: none in the bar, so they sit on the same line as the other fields
        .'.patches-filter-dock__condition .row,.patches-filter-dock__value .row{margin:0}'
        .'.patches-filter-dock__condition .form-group,.patches-filter-dock__value .form-group,.patches-filter-dock__value [class*=col-],.patches-filter-dock__condition [class*=col-]{margin:0;padding:0}'
        .'.patches-filter-dock__value .properties-form,.patches-filter-dock__value .properties-form>div{margin:0}'
        // core's select that adds a filter, moved here: it fills the slot and its list opens upwards (the bar sits at the bottom)
        .'.patches-filter-dock__field .available-filters{float:none;width:100%;margin:0!important;padding:0!important}'
        .'.patches-filter-dock__field .available-filters>.chosen-container,.patches-filter-dock__field .chosen-container{display:block;margin:0;vertical-align:middle}'
        .'.patches-filter-dock__field .chosen-container{width:100%!important}'
        .'.patches-filter-dock .chosen-container .chosen-drop{top:auto;bottom:100%}'
        .'.patches-filter-dock .chosen-container-active.chosen-with-drop .chosen-single{border-radius:0 0 4px 4px}'
        .'.patches-filter-dock .dropdown-menu{top:auto;bottom:100%}'
        .'.patches-filter-dock__add{flex:none;white-space:nowrap}'
        // the Filters tab leaves room for the bar (its height is measured by the script)
        .'#filters{padding-bottom:var(--patches-dock-space,0)}'
        .'#leadlist_filters .patches-field-input{text-overflow:ellipsis;cursor:default}'
        .'#leadlist_filters .patches-filter-row{display:flex;align-items:center;gap:6px}'
        .'#leadlist_filters .patches-filter-row>.properties-form{flex:1 1 auto;min-width:0}'
        .'#leadlist_filters .patches-filter-actions{flex:none;display:flex;align-items:center;gap:6px;height:34px}'
        .'#leadlist_filters .patches-filter-badge{position:relative;z-index:2;flex:none;display:inline-flex;box-sizing:border-box;height:29px;min-width:0;max-width:29px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;border-radius:14.5px;font-size:14px;color:#fff;text-decoration:none;transition:max-width .2s ease,box-shadow .2s ease}'
        // the clone badge: the lilac of the test badge of the campaign builder (#patches-steps-container .patches-badge--test)
        .'.patches-badge--test{background-color:#7e57c2}'
        .'#leadlist_filters .patches-filter-badge.hide{display:none}'
        .'#leadlist_filters .patches-filter-badge:hover{z-index:3;max-width:200px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;color:#fff}'
        .'#leadlist_filters .patches-filter-badge>i{flex:0 0 29px;width:29px;height:29px;font-size:21px;display:flex;align-items:center;justify-content:center}'
        .'#leadlist_filters .patches-filter-badge .patches-icon-badge__text{padding:0 12px 0 0;opacity:0;transition:opacity .15s ease .05s}'
        .'#leadlist_filters .patches-filter-badge:hover .patches-icon-badge__text{opacity:1}';

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

        $customContentEvent->addContent(
            '<style>'.AbstractListStatusSubscriber::BADGE_STYLE.self::STYLE.'</style>'
            .'<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-filter-duplicate.js')
            .';mauticPatchesFilterDuplicateApi.install(mQuery,Mautic);</script>'
        );
    }
}
