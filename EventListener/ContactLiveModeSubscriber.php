<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows whether the live mode of the contact list is on. Core's button (#liveModeButton, toggleLiveLeadListUpdate) only
 * adds / removes the class "active" on itself, which barely changes how the ghost button looks: there was no way to tell
 * if the list was updating. While it is on, the refresh icon becomes the same spinning loader the segment's Update line
 * uses (Remix Icon loader-3-line as an inline SVG turned by core's 'ri-spin'); off, the normal icon is back.
 *
 * Core's onclick runs first (the target), this document handler after it (bubbling), so it reads the new state of the
 * button. The glyph of the <i> is hidden by a class (::before content none) and the SVG is put inside the <i>, so core's
 * tooltip on the <i> keeps working. Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT
 * on 'page.header.left').
 */
class ContactLiveModeSubscriber implements EventSubscriberInterface
{
    private const SPINNER_SVG = '<svg class="mautic-patches-live-spin" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" style="display:inline-block;vertical-align:-0.125em;animation:ri-spin 1s linear infinite;"><path d="M3.05469 13H5.07065C5.55588 16.3923 8.47329 19 11.9998 19C15.5262 19 18.4436 16.3923 18.9289 13H20.9448C20.4474 17.5 16.6323 21 11.9998 21C7.36721 21 3.55213 17.5 3.05469 13ZM3.05469 11C3.55213 6.50005 7.36721 3 11.9998 3C16.6323 3 20.4474 6.50005 20.9448 11H18.9289C18.4436 7.60771 15.5262 5 11.9998 5C8.47329 5 5.55588 7.60771 5.07065 11H3.05469Z"/></svg>';

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

        $customContentEvent->addContent('<script>'.self::script().'</script>');
    }

    public static function script(): string
    {
        return '(function(){'
            .'if(window.mauticPatchesLiveSpin){return;}'
            .'window.mauticPatchesLiveSpin=true;'
            .'var style=document.createElement("style");'
            .'style.textContent=".mautic-patches-live-on::before{content:none!important}";'
            .'document.head.appendChild(style);'
            .'var SPINNER='.json_encode(self::SPINNER_SVG, JSON_UNESCAPED_SLASHES).';'
            .'function sync(button){'
            .'var icon=button.querySelector("i");'
            .'if(!icon){return;}'
            .'var on=button.classList.contains("active"),spinner=icon.querySelector(".mautic-patches-live-spin");'
            .'if(on&&!spinner){icon.classList.add("mautic-patches-live-on");icon.insertAdjacentHTML("beforeend",SPINNER);}'
            .'else if(!on&&spinner){spinner.remove();icon.classList.remove("mautic-patches-live-on");}'
            .'}'
            .'document.addEventListener("click",function(e){'
            .'var button=e.target&&e.target.closest?e.target.closest("#liveModeButton"):null;'
            .'if(button){sync(button);}'
            .'});'
            .'})();';
    }
}
