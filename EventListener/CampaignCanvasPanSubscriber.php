<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A usability change, not a bug: the campaign canvas is a 10000 x 10000 px area that only moves with the scroll bars or
 * the wheel, which is slow on a large campaign. With the middle button (the wheel pressed) held down on the canvas, the
 * mouse drags it, like a hand tool: the cursor becomes the closed hand and the nearest scrollable ancestor of the
 * pressed element is scrolled by the same distance, so it works on the builder (`.builder-content`) and on the Preview
 * tab of the campaign page (`#preview-container`) without knowing either.
 *
 * Only a middle press inside `#campaign-builder` starts it; the left button is still jsPlumb's (it drags nodes). The
 * mousedown is cancelled so the browser's own auto-scroll (the circle with arrows) does not start, and the auxclick that
 * follows is cancelled too so a middle press that ends on a link does not open a tab. The listeners are on the document
 * (capture), so they cover pages that Mautic loads over ajax. The scrolling fires the scroll event core already listens
 * to, so jsPlumb repaints the lines as it does for the scroll bar.
 *
 * Injected the same way as CampaignBuilderOverlaySubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignCanvasPanSubscriber implements EventSubscriberInterface
{
    public const STYLE = 'body.mautic-patches-panning,body.mautic-patches-panning #campaign-builder *{cursor:grabbing!important;user-select:none!important}';

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
            .'if(window.mauticPatchesCanvasPan){return;}'
            .'window.mauticPatchesCanvasPan=true;'
            .'var style=document.createElement("style");style.textContent='.json_encode(self::STYLE).';document.head.appendChild(style);'
            .'var pan=null;'
            .'function onCanvas(e){return e.target&&e.target.closest&&e.target.closest("#campaign-builder");}'
            // the nearest ancestor that really scrolls
            .'function scroller(el){'
            .'for(;el&&el!==document.body;el=el.parentElement){'
            .'var c=getComputedStyle(el);'
            .'if(/(auto|scroll)/.test(c.overflowX+c.overflowY)&&(el.scrollWidth>el.clientWidth||el.scrollHeight>el.clientHeight)){return el;}'
            .'}'
            .'return null;'
            .'}'
            .'function stop(){pan=null;document.body.classList.remove("mautic-patches-panning");}'
            .'document.addEventListener("mousedown",function(e){'
            .'if(e.button!==1||!onCanvas(e)){return;}'
            .'var el=scroller(e.target);'
            .'if(!el){return;}'
            .'e.preventDefault();'
            .'pan={el:el,x:e.clientX,y:e.clientY,left:el.scrollLeft,top:el.scrollTop};'
            .'document.body.classList.add("mautic-patches-panning");'
            .'},true);'
            .'document.addEventListener("mousemove",function(e){'
            .'if(!pan){return;}'
            // the button was released outside the window: stop
            .'if(!(e.buttons&4)){stop();return;}'
            .'pan.el.scrollLeft=pan.left-(e.clientX-pan.x);'
            .'pan.el.scrollTop=pan.top-(e.clientY-pan.y);'
            .'},true);'
            .'document.addEventListener("mouseup",function(e){if(e.button===1){stop();}},true);'
            .'window.addEventListener("blur",stop);'
            .'document.addEventListener("auxclick",function(e){if(e.button===1&&onCanvas(e)){e.preventDefault();}},true);'
            .'})();';
    }
}
