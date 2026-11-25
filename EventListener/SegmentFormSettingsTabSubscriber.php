<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * On the segment form (new and edit), the fields of the right column (category, visible to others, preference center,
 * published) move into a "Configurações" tab, the first of the tabs and the active one on every open. The right column
 * goes away and the main column takes the full width.
 *
 * Core's template has no hook for it, and is not touched: a script moves the field rows, on the first load and after
 * every Mautic ajax page load (it wraps Mautic.onPageLoad, like SegmentFiltersTabSubscriber). The rows stay inside the
 * same <form>, so saving is unchanged. It acts only on a form that has the `#details` and `#filters` panes and a
 * `.col-md-3.bdr-l` column in the same `.box-layout`, once per form. The tab turns red when a moved field has an error,
 * as core does for its own tabs.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentFormSettingsTabSubscriber implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator)
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

        $customContentEvent->addContent('<script>'.self::script($this->translator->trans('mautic.patches.segment.settings_tab')).'</script>');
    }

    public static function script(string $title): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentFormSettingsTab){return;}'
            .'window.mauticPatchesSegmentFormSettingsTab=true;'
            .'function moveFields(){'
            .'mQuery("form .box-layout").each(function(){'
            .'var $layout=mQuery(this);'
            .'var $side=$layout.children(".col-md-3.bdr-l");'
            .'var $details=$layout.find("#details");'
            .'var $nav=$layout.find("a[href=\"#details\"]").closest("ul");'
            .'if(!$side.length||!$details.length||!$layout.find("#filters").length||!$nav.length||$layout.find("#segment-settings-container").length){return;}'
            .'var $pane=mQuery("<div>",{"class":"tab-pane bdr-w-0 active",id:"segment-settings-container"});'
            .'$pane.append($side.find(".pr-lg").children());'
            // The pane is shown at once: the other panes fade in, and the tab is the active one.
            .'$nav.children("li").removeClass("active");'
            .'$details.removeClass("active in");'
            .'$details.parent().prepend($pane);'
            .'var $a=mQuery("<a>",{href:"#segment-settings-container",role:"tab","data-toggle":"tab"}).text('.json_encode($title, JSON_UNESCAPED_UNICODE).');'
            .'if($pane.find(".has-error").length){$a.addClass("text-danger").append(" <i class=\"ri-alert-line\"></i>");}'
            .'$nav.prepend(mQuery("<li>",{"class":"active"}).append($a));'
            .'$side.remove();'
            .'$layout.children(".col-md-9").removeClass("col-md-9").addClass("col-md-12");'
            .'});'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);moveFields();return result;};'
            .'mQuery(moveFields);'
            .'})();';
    }
}
