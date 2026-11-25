<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A layout choice for the header of the Campaigns and Segments pages (list and item page): the toolbar column (Edit button and
 * company) is narrower, col-sm-4 col-md-4 instead of core's col-sm-6 col-md-7, and the title column wider, col-sm-8
 * col-md-8 instead of col-sm-6 col-md-5. Core's pageheader.html.twig is shared by every page and is not touched: on a
 * campaign or segment route a small script swaps the column classes of the `.page-header` (the header is
 * rendered on every ajax page load too, so the script runs each time). Other pages and bundles keep core's columns.
 *
 * The detail (view) and form (edit, new) pages of the campaign, segment, contact, email, custom object and custom item use 7/5 instead
 * (title col-sm-7 col-md-7, toolbar col-sm-5 col-md-5); the campaign and segment list pages keep 8/4.
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

        // Forwarded sub-requests (custom item save/apply) carry no _route: read the main request.
        $request = $this->requestStack->getMainRequest();
        $route   = (string) $request?->attributes->get('_route');
        if (self::isDetailPage($route, (string) $request?->attributes->get('objectAction'))) {
            $customContentEvent->addContent('<script>'.self::script(7, 5).'</script>');

            return;
        }

        if (!str_starts_with($route, 'mautic_campaign_') && !str_starts_with($route, 'mautic_segment_')) {
            return;
        }

        $customContentEvent->addContent('<script>'.self::script().'</script>');
    }

    public static function isDetailPage(string $route, string $objectAction): bool
    {
        if (in_array($route, [
            'mautic_custom_object_view', 'mautic_custom_item_view',
            'mautic_custom_object_edit', 'mautic_custom_object_new', 'mautic_custom_item_edit', 'mautic_custom_item_new',
            'mautic_custom_object_save', 'mautic_custom_item_save', 'mautic_custom_object_clone', 'mautic_custom_item_clone',
            'mautic_custom_item_edit_redirect_to_contact', 'mautic_custom_item_new_redirect_to_contact',
        ], true)) {
            return true;
        }

        return in_array($objectAction, ['view', 'edit', 'new', 'clone'], true) && in_array($route, [
            'mautic_campaign_action',
            'mautic_segment_action',
            'mautic_contact_action',
            'mautic_email_action',
        ], true);
    }

    public static function script(int $titleCols = 8, int $toolbarCols = 4): string
    {
        return '(function(){'
            .'function swap(){'
            .'document.querySelectorAll(".page-header").forEach(function(header){'
            .'var title=header.querySelector(".col-md-5.va-m");'
            .'var toolbar=header.querySelector(".col-md-7.va-m");'
            .'if(title&&toolbar&&!header.hasAttribute("data-patches-cols")){'
            .'header.setAttribute("data-patches-cols","1");'
            .'title.className="col-xs-12 col-sm-'.$titleCols.' col-md-'.$titleCols.' va-m";'
            .'toolbar.className="col-xs-12 col-sm-'.$toolbarCols.' col-md-'.$toolbarCols.' va-m";'
            .'}'
            .'});'
            .'}'
            // the right column is parsed after this script on the first load; an ajax load inserts the header after the script ran
            .'swap();setTimeout(swap,0);setTimeout(swap,200);'
            .'})();';
    }
}
