<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A layout choice for the header of the Campaigns pages (list and campaign page): the toolbar column (Edit button and
 * company) is narrower, col-sm-4 col-md-4 instead of core's col-sm-6 col-md-7, and the title column wider, col-sm-8
 * col-md-8 instead of col-sm-6 col-md-5. Core's pageheader.html.twig is shared by every page and is not touched: on a
 * campaign route a small script swaps the column classes of the `.page-header` it was rendered in (the header is
 * rendered on every ajax page load too, so the script runs each time). Other pages and bundles keep core's columns.
 *
 * Injected the same way as CampaignViewOrderSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignPageHeaderColumnsSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
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

        $route = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        if (!str_starts_with($route, 'mautic_campaign_')) {
            return;
        }

        $customContentEvent->addContent('<script>'.self::script().'</script>');
    }

    public static function script(): string
    {
        return '(function(){'
            .'var me=document.currentScript;'
            .'function swap(){'
            .'var header=me&&me.closest?me.closest(".page-header"):null;'
            .'if(!header){return;}'
            .'var title=header.querySelector(".col-md-5.va-m");'
            .'var toolbar=header.querySelector(".col-md-7.va-m");'
            .'if(title){title.className="col-xs-12 col-sm-8 col-md-8 va-m";}'
            .'if(toolbar){toolbar.className="col-xs-12 col-sm-4 col-md-4 va-m";}'
            .'}'
            // the right column is parsed after this script on the first load
            .'swap();setTimeout(swap,0);'
            .'})();';
    }
}
