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
        .'#leadlist_filters .segment-filter>.panel-body{display:flex;align-items:flex-start}'
        .'#leadlist_filters .segment-filter>.panel-body::before,#leadlist_filters .segment-filter>.panel-body::after{display:none}'
        .'#leadlist_filters .segment-filter>.panel-body>div{float:none;box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue{flex:0 0 calc(8.3333% - 8px);width:auto;padding:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue.hide{display:block!important;visibility:hidden}'
        .'#leadlist_filters .segment-filter>.panel-body>.field-name{flex:0 0 calc(33.3333% - 8px);padding:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-3.padding-none{flex:0 0 calc(16.6667% - 8px)}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-6{flex:0 0 calc(41.6667% - 8px)}'
        // the floating bar at the bottom centre: the same 12 slots (1 + 4 + 2 + 5) as a filter row; only layout
        .'.patches-filter-dock{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:1000;box-sizing:border-box;width:min(1430px,92vw);margin:0;border:1px solid var(--border-subtle,#e0e0e0);border-radius:12px;box-shadow:none!important}'
        .'.patches-filter-dock:hover{box-shadow:none!important;transform:translateX(-50%)}'
        .'.patches-filter-dock,.patches-filter-dock:hover,.patches-filter-dock>.panel-body,.patches-filter-dock:hover>.panel-body{background:var(--background-brand,#4e5e9e)!important}'
        .'.patches-filter-dock>.panel-body{display:flex;align-items:center;padding:12px 8px 12px 16px}'
        .'.patches-filter-dock>.panel-body>div{box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'.patches-filter-dock__glue{flex:0 0 calc(8.3333% - 8px)}.patches-filter-dock__field{flex:0 0 calc(33.3333% - 8px)}'
        .'.patches-filter-dock__condition{flex:0 0 calc(16.6667% - 8px)}.patches-filter-dock__value{flex:0 0 calc(41.6667% - 8px);display:flex;align-items:center;gap:8px}'
        .'.patches-filter-dock__value>input{flex:1 1 auto;min-width:0}'
        .'.patches-filter-dock__add{flex:none;white-space:nowrap}'
        .'#leadlist_filters .patches-field-input{text-overflow:ellipsis;cursor:default}'
        .'#leadlist_filters .patches-filter-row{display:flex;align-items:flex-start;gap:6px}'
        .'#leadlist_filters .patches-filter-row>.properties-form{flex:1 1 auto;min-width:0}'
        .'#leadlist_filters .patches-filter-actions{flex:none;display:flex;align-items:center;gap:6px;height:34px}'
        .'#leadlist_filters .patches-filter-badge{position:relative;z-index:2;flex:none;display:inline-flex;box-sizing:border-box;height:29px;min-width:0;max-width:29px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;border-radius:14.5px;font-size:14px;color:#fff;text-decoration:none;transition:max-width .2s ease,box-shadow .2s ease}'
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
