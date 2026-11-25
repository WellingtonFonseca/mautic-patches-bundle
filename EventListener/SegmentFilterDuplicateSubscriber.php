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
        // one row: {E/OU} {field name} {condition} {value} {badges}
        .'#leadlist_filters .segment-filter>.panel-body{display:flex;align-items:flex-start;gap:8px}'
        .'#leadlist_filters .segment-filter>.panel-body::before,#leadlist_filters .segment-filter>.panel-body::after{display:none}'
        .'#leadlist_filters .segment-filter>.panel-body>div{float:none;width:auto;min-width:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue{flex:0 0 84px;padding:0;margin:0}'
        .'#leadlist_filters .segment-filter>.panel-body>.panel-glue.hide{display:block!important;visibility:hidden}'
        .'#leadlist_filters .segment-filter>.panel-body>.field-name{flex:0 0 20%;align-self:center}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-3.padding-none{flex:0 0 22%}'
        .'#leadlist_filters .segment-filter>.panel-body>.col-sm-6{flex:1 1 0}'
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
