<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Filtros tab of the segment form drawn by the plugin: its own list of rows (E / OU, field, condition, value, badges)
 * and the floating add bar, over Mautic's filter area, which stays in the page hidden as the data layer (core keeps
 * posting, validating and saving it). The behaviour is in Resources/js/segment-filter-form.js (adapter) and
 * Resources/js/segment-filter-ui.js (rows and bar); both only act where the segment form (#leadlist_filters inside
 * form[name=leadlist], not a Dynamic Content filter) exists.
 *
 * Kill switch: parameter patches_segment_filters_ui (default true). false = nothing is injected here and
 * SegmentFilterDuplicateSubscriber / SegmentFilterSelectorSubscriber (the previous patches) run as before.
 *
 * Injected the same way as SegmentInChipsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left'),
 * only on the segment pages (route mautic_segment_action).
 */
class SegmentFilterUiSubscriber implements EventSubscriberInterface
{
    public const PARAMETER = 'patches_segment_filters_ui';
    public const ROUTE     = 'mautic_segment_action';

    // one row on the 12 slots, as the filter of Mautic's list had it: E/OU 1, field name 4, condition 2, value with the badges 5 (each box keeps an 8px gap inside its slots)
    public const STYLE = '#filters.patches-sf-on>#leadlist_filters,#filters.patches-sf-on>.form-group{display:none}'
        .'.patches-sf-row.panel{border:1px solid color-mix(in srgb,currentColor 28%,transparent)}'
        // a group of AND filters is one frame: the shared edge has no border and is square, and there is no gap between the panels
        .'.patches-sf-row.patches-sf-join-down{margin-bottom:0;border-bottom-width:0;border-bottom-left-radius:0;border-bottom-right-radius:0}'
        .'.patches-sf-row.panel{border-top-width:0;border-top-left-radius:0;border-top-right-radius:0}'
        // the column titles of a group of filters: in the list at the start of the group, and fixed under the top bar while the group is on screen
        .'.patches-sf-head{box-sizing:border-box;padding:8px 15px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,currentColor 28%,transparent);border-bottom-width:0;border-radius:var(--patches-sf-radius,12px) var(--patches-sf-radius,12px) 0 0;background-image:linear-gradient(color-mix(in srgb,currentColor 9%,transparent),color-mix(in srgb,currentColor 9%,transparent))}'
        .'.patches-sf-head__main{display:flex;align-items:center;opacity:.8}'
        .'.patches-sf-head__main>span{box-sizing:border-box;min-width:0;margin:0 8px 0 0;overflow:hidden;white-space:nowrap}'
        .'.patches-sf-head__move{flex:none;width:29px}.patches-sf-head__glue{flex:0 1 calc(8.3333% - 8px)}.patches-sf-head__field{flex:0 1 calc(33.3333% - 8px)}'
        .'.patches-sf-head__operator{flex:0 1 calc(16.6667% - 8px)}.patches-sf-head__value{flex:0 1 calc(41.6667% - 8px)}'
        .'.patches-sf-head.patches-sf-sticky{position:fixed;z-index:900}'
        .'.patches-sf-main{display:flex;align-items:center;min-width:0}'
        .'.patches-sf-row>.panel-body::before,.patches-sf-row>.panel-body::after{display:none}'
        .'.patches-sf-main>div{box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'.patches-sf-glue{flex:0 1 calc(8.3333% - 8px)}.patches-sf-glue.is-first{visibility:hidden}'
        .'.patches-sf-field{flex:0 1 calc(33.3333% - 8px)}.patches-sf-operator{flex:0 1 calc(16.6667% - 8px)}'
        .'.patches-sf-value{flex:0 1 calc(41.6667% - 8px);display:flex;align-items:center;gap:6px}'
        .'.patches-sf-valuecell{flex:1 1 auto;min-width:0}'
        .'.patches-sf-valuecell>.col-sm-6{float:none;width:auto;padding:0}'
        // the widgets core loads for the value carry row / form-group margins: none, so every item of the row sits on the same center line
        .'.patches-sf-valuecell .properties-form .row,.patches-sf-valuecell .properties-form>div{margin:0}'
        .'.patches-sf-valuecell .properties-form .form-group{margin:0}'
        .'.patches-sf-valuecell .properties-form [class*=col-]{padding-top:0;padding-bottom:0}'
        // chosen started while its filter was hidden has no width
        .'.patches-sf-valuecell .chosen-container{width:100%!important}'
        .'.patches-sf-valuecell .alert{display:none}'
        .'.patches-sf-row>.panel-body>.patches-sf-alerts{margin:12px 8px 0 calc(8.3333% + 37px);display:flex;flex-direction:column;gap:8px}'
        .'.patches-sf-alerts:empty{display:none}.patches-sf-alerts .alert{margin:0}'
        .'.patches-sf-field .patches-field-input{text-overflow:ellipsis;caret-color:transparent}'
        // the dock has no drag badge: its left padding is the badge (29px) and its gap (8px) wider, so its cells line up with the rows
        .'.patches-filter-dock>.panel-body{padding-left:53px}'
        .'.patches-sf-row .patches-filter-badge.patches-sf-drag{margin:0 8px 0 0;cursor:move;touch-action:none}'
        .'.patches-sf-row.ui-sortable-helper{box-shadow:0 4px 12px rgba(0,0,0,.3)}'
        .'.patches-sf-field .patches-field-group{display:flex;width:100%}'
        .'.patches-sf-field .patches-field-group .input-group-addon{display:flex;align-items:center;width:auto;white-space:nowrap}'
        .'.patches-sf-field .patches-field-group .patches-field-input{flex:1 1 auto;min-width:0;width:100%}'
        .'.patches-sf-row .patches-filter-actions{flex:none;display:flex;align-items:center;gap:6px;height:34px}'
        .'.patches-sf-row .patches-filter-badge{position:relative;z-index:2;flex:none;display:inline-flex;box-sizing:border-box;height:29px;min-width:0;max-width:29px;margin:0;padding:0;gap:0;'
        .'justify-content:flex-start;overflow:hidden;border-radius:14.5px;font-size:14px;color:#fff;text-decoration:none;transition:max-width .2s ease,box-shadow .2s ease}'
        // the clone badge: the lilac of the test badge of the campaign builder (#patches-steps-container .patches-badge--test)
        .'.patches-badge--test{background-color:#7e57c2}'
        .'.patches-sf-row .patches-filter-badge.patches-off{visibility:hidden;pointer-events:none}'
        .'.patches-sf-row .patches-filter-badge:hover{z-index:3;max-width:200px;box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;color:#fff}'
        .'.patches-sf-row .patches-filter-badge>i{flex:0 0 29px;width:29px;height:29px;font-size:21px;display:flex;align-items:center;justify-content:center}'
        .'.patches-sf-row .patches-filter-badge .patches-icon-badge__text{padding:0 12px 0 0;opacity:0;transition:opacity .15s ease .05s}'
        .'.patches-sf-row .patches-filter-badge:hover .patches-icon-badge__text{opacity:1}';

    public function __construct(private ?CoreParametersHelper $coreParametersHelper = null, private ?RequestStack $requestStack = null)
    {
    }

    public static function enabled(?CoreParametersHelper $coreParametersHelper): bool
    {
        return null === $coreParametersHelper || (bool) $coreParametersHelper->get(self::PARAMETER, true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext() || !self::enabled($this->coreParametersHelper)) {
            return;
        }

        $route = $this->requestStack?->getCurrentRequest()?->attributes->get('_route');
        if (null !== $route && self::ROUTE !== $route) {
            return;
        }

        $js = __DIR__.'/../Resources/js/';

        $customContentEvent->addContent(
            '<style>'.AbstractListStatusSubscriber::BADGE_STYLE.SegmentFilterDuplicateSubscriber::DOCK_STYLE.self::STYLE.'</style>'
            .'<script>'.file_get_contents($js.'segment-filter-form.js').file_get_contents($js.'segment-filter-ui.js')
            .';MauticPatchesSegmentFilterUi.install(mQuery,Mautic);</script>'
        );
    }
}
