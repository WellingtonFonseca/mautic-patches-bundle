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
    // the floating add bar of the segment filters (used by the new screen only)
    public const DOCK_STYLE = ''
        // the floating bar at the bottom centre: the same 12 slots (1 + 4 + 2 + 5) as a filter row; only layout
        .'.patches-filter-dock{position:fixed;left:50%;bottom:56px;transform:translateX(-50%);z-index:1000;box-sizing:border-box;width:min(1430px,92vw);margin:0;border:1px solid var(--border-subtle,#e0e0e0);border-radius:12px;box-shadow:none!important}'
        .'.patches-filter-dock.patches-dock-off{display:none!important}'
        .'.patches-filter-dock:hover{box-shadow:none!important;transform:translateX(-50%)}'
        .'.patches-filter-dock,.patches-filter-dock:hover{background:#2C355A!important}'
        .'.patches-filter-dock>.panel-body,.patches-filter-dock:hover>.panel-body{background:transparent!important;border-radius:inherit}'
        .'.patches-filter-dock>.panel-body{display:flex;flex-direction:column;gap:8px;padding:12px 64px 12px 72px}'
        .'.patches-filter-dock__main{display:flex;align-items:center;min-width:0}'
        .'.patches-filter-dock__main>div{box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        // the date builder: the Tipo select on the line above, starting where the value starts (after the glue, field and condition slots: 58.3333%); its error under the row
        .'.patches-filter-dock__type,.patches-filter-dock__error{box-sizing:border-box;padding-left:58.3333%;min-width:0}'
        .'.patches-filter-dock__type{display:flex;align-items:center;gap:8px}.patches-filter-dock__type:empty,.patches-filter-dock__error:empty{display:none}'
        .'.patches-filter-dock__type .patches-date__label{color:#c9cfe6;font-size:12px}'
        .'.patches-filter-dock__error{color:#ff8389;font-size:12px}'
        .'.patches-filter-dock .patches-date__check{color:#fff}'
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
        // the suggestions of the including / excluding chips open upwards too (the inline top:100% of the chips needs the !important)
        .'.patches-filter-dock .segment-in-chips .dropdown-menu{top:auto!important;bottom:100%!important;margin:0 0 2px}'
        .'.patches-filter-dock__add{flex:none;white-space:nowrap}'
        // the Filters tab leaves room for the bar (its height is measured by the script)
        .'#filters{padding-bottom:var(--patches-dock-space,0)}';

    public const STYLE = '#filters.patches-sf-on>#leadlist_filters,#filters.patches-sf-on>.form-group{display:none}'
        .'.patches-sf-row.panel{border:1px solid color-mix(in srgb,currentColor 28%,transparent)}'
        // a group of AND filters is one frame: the shared edge has no border and is square, and there is no gap between the panels
        .'.patches-sf-row.patches-sf-join-down{margin-bottom:0;border-bottom-width:0;border-bottom-left-radius:0;border-bottom-right-radius:0}'
        .'.patches-sf-row.panel{border-top-width:0;border-top-left-radius:0;border-top-right-radius:0}'
        // the column titles of a group of filters (the font, weight, padding and background of the header of a core table: .table>thead>tr>th): in the list at the start of the group, and fixed under the top bar while the group is on screen
        .'.patches-sf-head{box-sizing:border-box;padding:8px 15px;font-size:14px;font-weight:700;line-height:1.3846;border:1px solid color-mix(in srgb,currentColor 28%,transparent);border-bottom-width:0;border-radius:var(--patches-sf-radius,12px) var(--patches-sf-radius,12px) 0 0;background-color:var(--layer-accent)}'
        .'.patches-sf-head__main{display:flex;align-items:center}'
        .'.patches-sf-head__main>span{box-sizing:border-box;min-width:0;margin:0 8px 0 0;overflow:hidden;white-space:nowrap}'
        .'.patches-sf-head__move{flex:none;width:48px}.patches-sf-head__glue{flex:0 1 calc(8.3333% - 8px)}.patches-sf-head__field{flex:0 1 calc(33.3333% - 8px)}'
        .'.patches-sf-head__operator{flex:0 1 calc(16.6667% - 8px)}.patches-sf-head__value{flex:1 1 0}.patches-sf-head__main>.patches-sf-head__actions{flex:none;width:99px;margin:0}'
        .'.patches-sf-head.patches-sf-sticky{position:fixed;z-index:900}'
        .'.patches-sf-main{display:flex;align-items:flex-start;min-width:0}'
        .'.patches-sf-row>.panel-body::before,.patches-sf-row>.panel-body::after{display:none}'
        .'.patches-sf-main>div{box-sizing:border-box;min-width:0;margin:0 8px 0 0}'
        .'.patches-sf-glue{flex:0 1 calc(8.3333% - 8px)}.patches-sf-glue.is-first{visibility:hidden}'
        .'.patches-sf-field{flex:0 1 calc(33.3333% - 8px)}.patches-sf-operator{flex:0 1 calc(16.6667% - 8px)}'
        .'.patches-sf-value{flex:1 1 0}'
        .'.patches-sf-valuecell{flex:1 1 auto;min-width:0}'
        .'.patches-sf-valuecell>.col-sm-6{float:none;width:auto;padding:0}'
        // the widgets core loads for the value carry row / form-group margins: none, so every item of the row sits on the same center line
        .'.patches-sf-valuecell .properties-form .row,.patches-sf-valuecell .properties-form>div{margin:0}'
        .'.patches-sf-valuecell .properties-form .form-group{margin:0}'
        .'.patches-sf-valuecell .properties-form [class*=col-]{padding:0}'
        // chosen started while its filter was hidden has no width
        .'.patches-sf-valuecell .chosen-container{width:100%!important}'
        .'.patches-sf-valuecell .alert{display:none}'
        .'.patches-sf-value>.patches-sf-alerts{margin:0;display:flex;flex-direction:column;gap:0}'
        // the alert is glued to the value field: straight bottom corners on the field, straight top corners on the alert
        .'.patches-sf-value:has(>.patches-sf-alerts:not(:empty)) .patches-sf-valuecell :is(.form-control,.chosen-single,.chosen-choices,.input-group-addon){border-bottom-left-radius:0;border-bottom-right-radius:0;margin-bottom:0}'
        .'.patches-sf-value>.patches-sf-alerts .alert{border-top-left-radius:0;border-top-right-radius:0;border-top-width:0}'
        .'.patches-sf-alerts:empty{display:none}.patches-sf-alerts .alert{margin:0}'
        .'.patches-date{flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px}.patches-date:empty{display:none}'
        .'.patches-date__pane{flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px}.patches-date__pane>.form-control{width:auto;flex:1 1 0;min-width:0}'
        .'.patches-date__pane>.patches-date__num{flex:0 0 80px}.patches-date__type{width:auto;flex:none}'
        .'.patches-date__check{display:inline-flex;align-items:center;gap:6px;margin:0;font-weight:400;white-space:nowrap}.patches-date__check input{margin:0}'
        .'.patches-date-on{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:8px}.patches-date__typebox{display:inline-flex;flex:none;align-items:center;gap:8px}.patches-date__pane>.patches-date__dir{flex:1.5 1 0}.patches-date-on>.properties-form{flex:1 1 0;min-width:0}.patches-date-core-off{display:none!important}'
        .'.patches-date .is-invalid{border-color:#da1e28;box-shadow:0 0 0 1px #da1e28}'
        .'.patches-sf-field .patches-field-input{text-overflow:ellipsis;caret-color:transparent}'
        // the dock has no move column: its left padding is that column (48px) and its gap (8px) wider, so its cells line up with the rows
                .'.patches-sf-main>.patches-sf-move{flex:none;min-width:48px;height:34px;display:flex;align-items:center}'
        .'.patches-sf-move .patches-filter-badge.patches-sf-drag{margin:0;cursor:move;touch-action:none}'
        .'.patches-sf-row.ui-sortable-helper{box-shadow:0 4px 12px rgba(0,0,0,.3)}'
        .'.patches-sf-field .patches-field-group{display:flex;width:100%}'
        .'.patches-sf-field .patches-field-group .input-group-addon{display:flex;align-items:center;flex:none;box-sizing:border-box;width:auto;min-width:33.333%;white-space:nowrap}'
        .'.patches-sf-field .patches-field-group .patches-field-input{flex:1 1 auto;min-width:0;width:100%}'
        .'.patches-sf-main>.patches-filter-actions{flex:none;margin:0;display:flex;align-items:center;gap:6px;height:34px}'
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
            '<style>'.AbstractListStatusSubscriber::BADGE_STYLE.self::DOCK_STYLE.self::STYLE.'</style>'
            .'<script>'.file_get_contents($js.'segment-filter-form.js').file_get_contents($js.'segment-filter-ui.js')
            .';MauticPatchesSegmentFilterUi.install(mQuery,Mautic);</script>'
        );
    }
}
