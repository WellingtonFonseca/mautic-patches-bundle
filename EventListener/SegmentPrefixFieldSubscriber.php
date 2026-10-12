<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the "Prefixo" select of the segment form (SegmentPrefixFormExtension) before the name. Core's template does not
 * list the field, so form_end() renders it at the bottom of the form; a script moves its row up, before the Nome row.
 * The field is the real one, with its own errors: only its place changes.
 *
 * It acts on a page that has `#leadlist_prefix` and `#leadlist_name`, once per field, on the first load and after every
 * Mautic ajax page load (it wraps Mautic.onPageLoad, like the other page scripts). Injected on 'page.header.left'.
 */
class SegmentPrefixFieldSubscriber implements EventSubscriberInterface
{
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
            .'if(window.mauticPatchesSegmentPrefix){return;}'
            .'window.mauticPatchesSegmentPrefix=true;'
            .'function move(){'
            .'var $prefix=mQuery("#leadlist_prefix"),$name=mQuery("#leadlist_name");'
            .'if(!$prefix.length||!$name.length||$prefix.data("moved")){return;}'
            .'$prefix.data("moved",true);'
            .'$prefix.closest(".form-group").insertBefore($name.closest(".form-group"));'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);move();return result;};'
            .'mQuery(move);'
            .'})();';
    }
}
