<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Puts a teal "Configurações" badge on top of that column and the "Distinct" block in the right column of the segment edit form (the one with Categoria, Publicado, Global, Ativo): a title
 * and the three rows (each in a `.row`, like core's rows there) of SegmentDistinctFormExtension (core's template does not list them, so form_end() renders them at the bottom).
 * The fields are the real ones, with their own errors: only their place changes.
 *
 * It acts on a page that has `#leadlist_distinct_enabled_1` and that column, once, on the first load and after every Mautic ajax page
 * load (it wraps Mautic.onPageLoad, like SegmentPrefixFieldSubscriber). Injected on 'page.header.left'.
 */
class SegmentDistinctTabSubscriber implements EventSubscriberInterface
{
    private const TITLE = 'Distinct';

    // The title is the Distinct badge of the segment details page (orange, icon "1"), open: the circle and its word, no hover animation.
    private const STYLE = AbstractListStatusSubscriber::BADGE_STYLE
        .'.patches-badge--distinct{background-color:#fb8c00}.patches-badge--settings{background-color:#00897b}'
        .'.patches-segment-distinct-title .patches-extra-badge{display:flex;width:100%;justify-content:center;box-sizing:border-box;height:40px;margin:0;padding:0;gap:0;border-radius:20px;font-size:14px}'
        .'.patches-segment-distinct-title .patches-extra-badge>i{flex:0 0 40px;width:40px;height:40px;display:flex;align-items:center;justify-content:center}'
        .'.patches-segment-distinct-title .patches-icon-badge__text{padding:0 14px 0 0}'
        // a long label (Visível para outros usuários) wraps in the narrow toggle column instead of being cut
        .'.col-md-3.bdr-l .col-xs-5 .control-label{white-space:normal;overflow:visible;text-overflow:clip}';

    public function __construct(private ?TranslatorInterface $translator = null)
    {
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

        $customContentEvent->addContent('<style>'.self::STYLE.'</style><script>'.self::script($this->translator?->trans('mautic.patches.segment.settings') ?? 'Configurações').'</script>');
    }

    public static function script(string $settingsTitle = 'Configurações'): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentDistinct){return;}'
            .'window.mauticPatchesSegmentDistinct=true;'
            .'function build(){'
            .'var $on=mQuery("#leadlist_distinct_enabled_1"),$side=mQuery(".col-md-3.bdr-l > div").first();'
            .'if(!$on.length||!$side.length||$on.data("moved")){return;}'
            .'$on.data("moved",true);'
            .'var $core=$side.children(".row");'
            .'var $rows=mQuery("#leadlist_distinct_enabled_1,#leadlist_distinct_remove_empty_1,#leadlist_distinct_fields").closest(".form-group");'
            .'mQuery("<div class=\"form-group col-xs-12 patches-segment-distinct-title\"><span class=\"patches-badge patches-badge--distinct patches-icon-badge patches-extra-badge\"><i class=\"ri-number-1\" aria-hidden=\"true\"></i><span class=\"patches-icon-badge__text\">"+'.json_encode(self::TITLE).'+"</span></span></div>").appendTo($side).wrap("<div class=\"row\"></div>");'
            .'mQuery("<div class=\"row\"><div class=\"form-group col-xs-12 patches-segment-distinct-title\"><span class=\"patches-badge patches-badge--settings patches-icon-badge patches-extra-badge\"><i class=\"ri-settings-3-line\" aria-hidden=\"true\"></i><span class=\"patches-icon-badge__text\">"+'.json_encode($settingsTitle).'+"</span></span></div></div>").prependTo($side);'
            .'$rows.each(function(){'
            .'var $r=mQuery(this),$in=$r.find("input[data-distinct-help]").first(),$row=mQuery("<div class=\"row\"></div>").appendTo($side);'
            .'if(!$in.length){$row.append($r);return;}'
            .'$r.removeClass("col-xs-12").appendTo(mQuery("<div class=\"col-xs-5\"></div>").appendTo($row));'
            .'$row.append(mQuery("<div class=\"col-xs-7\"><div class=\"alert alert-info\"></div></div>")).find(".alert").text($in.attr("data-distinct-help"));'
            .'});'
            // the "?" tooltips of core's toggles (Visível para outros usuários, ...) go into the same alert beside the toggle
            .'$core.each(function(){'
            .'var $g=mQuery(this).children(".form-group"),$l=$g.find("label[data-toggle=tooltip]").first(),t=$l.attr("data-original-title")||$l.attr("title")||"";'
            .'if(!$l.length||!t){return;}'
            .'try{$l.tooltip("destroy");}catch(e){}'
            .'$l.removeAttr("data-toggle data-original-title title").find("i").remove();'
            .'$g.removeClass("col-xs-12").wrap("<div class=\"col-xs-5\"></div>").parent().after(mQuery("<div class=\"col-xs-7\"><div class=\"alert alert-info\"></div></div>")).next().find(".alert").text(t);'
            .'});'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);build();return result;};'
            .'mQuery(build);'
            .'})();';
    }
}
