<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixRepository;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A "Prefixo" column in the campaign list, before Nome, plain text, sortable like the other headers (the order is done by
 * CampaignPrefixRepository). Core's list template has no hook for a column, and is not
 * touched: each row carries a hidden marker with its prefix (customContent('campaign.name'), the hook the row already
 * has) and a script, on the list page, adds the header and one cell per row from those markers. It runs on the first
 * load and after every Mautic ajax page load (sorting, paging, search), when the table is drawn again.
 */
class CampaignPrefixColumnSubscriber implements EventSubscriberInterface
{
    public function __construct(private CampaignPrefixStore $store, private TranslatorInterface $translator, private RequestStack $requestStack)
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
            $title = $this->translator->trans('mautic.patches.campaign.prefix');
            $customContentEvent->addContent('<script>'.self::script(
                $title,
                $this->translator->trans('mautic.core.sort.ascending.description', ['%header%' => $title]),
                $this->translator->trans('mautic.core.sort.descending.description', ['%header%' => $title])
            ).'</script>');

            return;
        }

        $campaign = $customContentEvent->getVars()['item'] ?? null;
        if ('campaign.name' === $context && $campaign instanceof Campaign) {
            $customContentEvent->addTemplate('@MauticPatches/Campaign/prefix_marker.html.twig', [
                'prefix' => $this->store->get($campaign->getId()),
                'sort'   => $this->sortState(),
            ]);
        }
    }

    /**
     * The "Prefixo" header's aria-sort, from the list's order in the session (the same one core's headers use).
     */
    private function sortState(): string
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (null === $session || CampaignPrefixRepository::SORT_KEY !== $session->get('mautic.campaign.orderby')) {
            return 'none';
        }

        return 'DESC' === strtoupper((string) $session->get('mautic.campaign.orderbydir', 'ASC')) ? 'descending' : 'ascending';
    }

    public static function script(string $title, string $labelAscending, string $labelDescending): string
    {
        return '(function(){'
            .'if(window.mauticPatchesCampaignPrefixColumn){return;}'
            .'window.mauticPatchesCampaignPrefixColumn=true;'
            .'function addColumn(){'
            .'var $table=mQuery("#campaignTable");'
            .'if(!$table.length||$table.find("th.col-campaign-prefix").length){return;}'
            .'var $head=$table.find("th.col-campaign-name");'
            .'if(!$head.length){return;}'
            .'var sort=$table.find(".campaign-prefix-data").first().attr("data-sort")||"none";'
            .'var icon=sort==="ascending"?"ri-arrow-up-line ri-lg fw-n":(sort==="descending"?"ri-arrow-down-line ri-lg fw-n":"ri-arrow-up-down-line ri-lg fw-n table-sort__icon-unsorted");'
            .'var $sorter=mQuery("<a>",{"aria-label":(sort==="ascending"?'.json_encode($labelDescending, JSON_UNESCAPED_UNICODE).':'.json_encode($labelAscending, JSON_UNESCAPED_UNICODE).'),"class":"btn btn-ghost btn-block jc-space-between table-sort",href:"javascript: void(0);",onclick:"Mautic.reorderTableData(\'campaign\',\''.CampaignPrefixRepository::SORT_KEY.'\',\'list\',\'.page-list\');"});'
            .'$sorter.append(mQuery("<span>",{"class":"fw-b"}).text('.json_encode($title, JSON_UNESCAPED_UNICODE).'),mQuery("<i>",{"class":icon,"aria-hidden":"true",focusable:"false"}));'
            .'$head.before(mQuery("<th>",{"class":"col-campaign-prefix pa-0",scope:"col","aria-sort":sort}).append($sorter));'
            .'$table.find("tbody tr").each(function(){'
            .'var $mark=mQuery(this).find(".campaign-prefix-data");'
            .'var $name=$mark.closest("td");'
            .'if(!$name.length){return;}'
            .'var prefix=$mark.attr("data-prefix")||"";'
            .'$name.before(mQuery("<td>",{"class":"col-campaign-prefix"}).text(prefix));'
            .'});'
            .'}'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addColumn();return result;};'
            .'mQuery(addColumn);'
            .'})();';
    }
}
