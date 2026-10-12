<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A "Prefixo" column in the segment list, before Nome. Core's list template has no hook for a column, and is not
 * touched: each row carries a hidden marker with its prefix (customContent('segment.name'), the hook the row already
 * has) and a script, on the list page, adds the header and one cell per row from those markers. It runs on the first
 * load and after every Mautic ajax page load (sorting, paging, search), when the table is drawn again.
 */
class SegmentPrefixColumnSubscriber implements EventSubscriberInterface
{
    public function __construct(private SegmentPrefixStore $store, private TranslatorInterface $translator)
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
        $context = $customContentEvent->getContext();

        if ('page.header.left' === $context) {
            $customContentEvent->addContent('<script>'.self::script($this->translator->trans('mautic.patches.segment.prefix')).'</script>');

            return;
        }

        $segment = $customContentEvent->getVars()['item'] ?? null;
        if ('segment.name' === $context && $segment instanceof LeadList) {
            $customContentEvent->addTemplate('@MauticPatches/Segment/prefix_marker.html.twig', ['prefix' => $this->store->get($segment->getId())]);
        }
    }

    public static function script(string $title): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentPrefixColumn){return;}'
            .'window.mauticPatchesSegmentPrefixColumn=true;'
            .'function addColumn(){'
            .'var $table=mQuery("#leadListTable");'
            .'if(!$table.length||$table.find("th.col-leadlist-prefix").length){return;}'
            .'var $head=$table.find("th.col-leadlist-name");'
            .'if(!$head.length){return;}'
            .'$head.before(mQuery("<th>",{"class":"col-leadlist-prefix pa-0",scope:"col"}).append(mQuery("<span>",{"class":"pa-md"}).text('.json_encode($title, JSON_UNESCAPED_UNICODE).')));'
            .'$table.find("tbody tr").each(function(){'
            .'var $mark=mQuery(this).find(".segment-prefix-data");'
            .'var $name=$mark.closest("td");'
            .'if(!$name.length){return;}'
            .'var prefix=$mark.attr("data-prefix")||"";'
            .'var $cell=mQuery("<td>",{"class":"col-leadlist-prefix"});'
            .'if(prefix!==""){$cell.append(mQuery("<span>",{"class":"label label-gray"}).text(prefix));}'
            .'$name.before($cell);'
            .'});'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addColumn();return result;};'
            .'mQuery(addColumn);'
            .'})();';
    }
}
