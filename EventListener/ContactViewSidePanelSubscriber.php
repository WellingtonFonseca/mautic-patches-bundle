<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A layout choice for the detail pages (a contact, a segment, a campaign, an email...): the right column (`.col-md-3.bdr-l`)
 * takes a quarter of the screen. This adds a "Mais >" button at the top of the main column (`.col-md-9`) that hides and
 * shows it, and the main column uses the whole width while it is hidden.
 *
 * The column ALWAYS starts hidden and the choice is never remembered (no localStorage, no cookie, no session): every time
 * a page is loaded it opens hidden, by design.
 *
 * Core's templates are not touched: a script, on the first load and after every Mautic ajax page load (it wraps
 * Mautic.onPageLoad), finds every right column of `#app-content` that has one `.col-md-9` sibling and is not inside a form
 * (the edit forms have a right column too, with the settings), and inserts the button. Each column is handled once (a data
 * flag), so running again on the same page never hides again what the user just showed.
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
            $this->translator->trans('mautic.patches.contact.side_panel.hide'),
            $this->translator->trans('mautic.patches.contact.side_panel.more')
        ).'</script>');
    }

    public static function script(string $showLabel, string $hideLabel, string $moreLabel): string
    {
        [$show, $hide, $more] = array_map(
            static fn (string $label): string => json_encode($label, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            [$showLabel, $hideLabel, $moreLabel]
        );

        return '(function(){'
            .'if(window.mauticPatchesContactSidePanel){return;}'
            .'window.mauticPatchesContactSidePanel=true;'
            .'var showLabel='.$show.',hideLabel='.$hide.',moreLabel='.$more.';'
            .'function place($right){'
            .'var $left=$right.siblings(".col-md-9");'
            .'if($left.length!==1||$right.closest("form").length||$left.data("mauticPatchesSidePanel")){return;}'
            .'$left.data("mauticPatchesSidePanel",true);'
            // Core's primary text button, like Editar: icon and label (the visible label is always "Mais"; the state is in the aria-label).
            .'var $button=mQuery("<button type=\"button\" class=\"btn btn-primary btn-nospin\"></button>")'
            .'.append(mQuery("<i class=\"ri-arrow-right-s-line\" aria-hidden=\"true\" focusable=\"false\"></i>"),document.createTextNode(" "),mQuery("<span></span>").text(moreLabel));'
            .'function apply(hidden){'
            .'$right.toggle(!hidden);'
            .'$left.css("width",hidden?"100%":"");'
            .'var label=hidden?showLabel:hideLabel;'
            .'$button.attr({"aria-label":label,title:label}).data("hidden",hidden);'
            .'window.dispatchEvent(new Event("resize"));'
            .'}'
            .'$button.on("click",function(){apply(!$button.data("hidden"));});'
            .'$left.prepend(mQuery("<div class=\"text-right pr-md pt-sm\"></div>").append($button));'
            .'apply(true);'
            .'}'
            .'function init(){mQuery("#app-content .col-md-3.bdr-l").each(function(){place(mQuery(this));});}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);init();return result;};'
            .'mQuery(init);'
            .'})();';
    }
}
