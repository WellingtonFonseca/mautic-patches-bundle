<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A layout choice for the contact page (Contacts > open one): the right column
 * (points, contact data, address...) takes a quarter of the screen. This adds
 * a button at the top of the middle column that hides and shows it, and the
 * middle column uses the whole width while it is hidden.
 *
 * The column ALWAYS starts hidden and the choice is never remembered (no
 * localStorage, no cookie, no session): every time a contact page is loaded
 * it opens hidden, by design.
 *
 * Core's template is not touched: a script, on the first load and after every
 * Mautic ajax page load (it wraps Mautic.onPageLoad), finds the page by its
 * `#lead-details` block, hides the `.col-md-3` next to it and inserts the
 * button. Each loaded page is handled once (a data flag), so running again on
 * the same page never hides again what the user just showed. Pages without
 * `#lead-details` are not affected.
 *
 * Injected the same way as CampaignViewOrderSubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class ContactViewSidePanelSubscriber implements EventSubscriberInterface
{
    public function __construct(private Translator $translator)
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

        $customContentEvent->addContent('<script>'.self::script(
            $this->translator->trans('mautic.patches.contact.side_panel.show'),
            $this->translator->trans('mautic.patches.contact.side_panel.hide')
        ).'</script>');
    }

    public static function script(string $showLabel, string $hideLabel): string
    {
        $show = json_encode($showLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        $hide = json_encode($hideLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        return '(function(){'
            .'if(window.mauticPatchesContactSidePanel){return;}'
            .'window.mauticPatchesContactSidePanel=true;'
            .'var showLabel='.$show.',hideLabel='.$hide.';'
            .'function init(){'
            .'var $left=mQuery("#lead-details").closest(".col-md-9");'
            .'if(!$left.length||$left.data("mauticPatchesSidePanel")){return;}'
            .'var $right=$left.siblings(".col-md-3");'
            .'if(!$right.length){return;}'
            .'$left.data("mauticPatchesSidePanel",true);'
            // Core's round icon button (btn-primary btn-icon btn-nospin, like the options dropdown), tooltip on the icon like core's button helper.
            .'var $icon=mQuery("<i class=\"ri-layout-right-2-line\" aria-hidden=\"true\" focusable=\"false\" data-toggle=\"tooltip\" data-placement=\"top\"></i>");'
            .'var $button=mQuery("<button type=\"button\" class=\"btn btn-primary btn-icon btn-nospin\"></button>").append($icon);'
            .'function apply(hidden){'
            .'$right.toggle(!hidden);'
            .'$left.css("width",hidden?"100%":"");'
            .'var label=hidden?showLabel:hideLabel;'
            .'$button.attr("aria-label",label).data("hidden",hidden);'
            .'$icon.attr({title:label,"data-original-title":label});'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'$button.on("click",function(){apply(!$button.data("hidden"));});'
            .'$left.prepend(mQuery("<div class=\"text-right pr-md pt-sm\"></div>").append($button));'
            .'apply(true);'
            .'if($icon.tooltip){$icon.tooltip();}'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);init();return result;};'
            .'mQuery(init);'
            .'})();';
    }
}
