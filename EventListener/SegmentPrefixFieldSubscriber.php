<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "Prefixo" field of the segment form, before the name ("PUCPR_POS | Onboarding": prefix "PUCPR_POS", name
 * "Onboarding"). The segment has no custom fields and core's template has no hook for a new one, so a script splits the
 * name input into two inputs, Prefixo and Nome. The real input (#leadlist_name) stays in the form, hidden, and always
 * holds the joined value, so what is submitted is the name as before. SegmentPrefixFormExtension makes it required.
 *
 * The split and the join mirror SegmentPrefix (the regex is the same). It acts on a page that has `#leadlist_name`, once
 * per input, on the first load and after every Mautic ajax page load (it wraps Mautic.onPageLoad, like the other page
 * scripts). Injected on 'page.header.left'.
 */
class SegmentPrefixFieldSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<script>'.self::script(
            $this->translator->trans('mautic.patches.segment.prefix'),
            $this->translator->trans('mautic.patches.segment.prefix.placeholder')
        ).'</script>');
    }

    public static function script(string $prefixLabel, string $prefixPlaceholder): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentPrefix){return;}'
            .'window.mauticPatchesSegmentPrefix=true;'
            .'function split(v){var m=/^([^\s|]+) \| (\S[\s\S]*)$/.exec(v);return m?[m[1],m[2]]:["",v];}'
            .'function addFields(){'
            .'var $full=mQuery("#leadlist_name");'
            .'if(!$full.length||$full.data("prefixed")){return;}'
            .'$full.data("prefixed",true);'
            .'var parts=split($full.val());'
            .'var $prefix=mQuery("<input>",{type:"text","class":"form-control",id:"leadlist_name_prefix",autocomplete:"off",placeholder:'.json_encode($prefixPlaceholder, JSON_UNESCAPED_UNICODE).',title:'.json_encode($prefixLabel, JSON_UNESCAPED_UNICODE).'}).css({width:"35%"}).val(parts[0]);'
            .'var $rest=mQuery("<input>",{type:"text","class":"form-control",id:"leadlist_name_rest",autocomplete:"off"}).css({flex:"1"}).val(parts[1]);'
            .'var $sep=mQuery("<span>").text("|").css({padding:"0 10px"});'
            .'var $row=mQuery("<div>").css({display:"flex","align-items":"center"}).append($prefix,$sep,$rest);'
            .'function sync(){'
            .'var p=mQuery.trim($prefix.val()),r=mQuery.trim($rest.val());'
            // Both empty: the joined value is empty too, so core's own "name is required" applies.
            .'$full.val(p===""&&r===""?"":p+" | "+r);'
            .'}'
            .'$prefix.on("input change",sync);$rest.on("input change",sync);'
            .'$full.hide().after($row);'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addFields();return result;};'
            .'mQuery(addFields);'
            .'})();';
    }
}
