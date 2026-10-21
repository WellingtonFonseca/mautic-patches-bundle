<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A usability change, not a bug: the circles of the nodes of the campaign builder (the points a line starts from or ends at)
 * are half hidden behind the node, because core gives the node z-index 1010 and the circle 1005. Only the half outside the node
 * could be grabbed, which takes aim. With the circle above the node it is whole and the whole circle takes the mouse.
 *
 * They also sit 70% outside the node (30% over it) instead of half and half: Resources/js/campaign-endpoints-outside.js moves the
 * offsets of the anchors core keeps in Mautic.campaignEndpointDefinitions.
 *
 * The mouse over a circle is a crosshair (the point to aim at when drawing a line), also over its svg.
 *
 * Above the node, below the trash button of the lines (CampaignConnectionTrashSubscriber, 1100). Injected the same way as
 * CampaignEventModalWidthSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignEndpointsOnTopSubscriber implements EventSubscriberInterface
{
    public const STYLE = '#CampaignCanvas .jtk-endpoint,#CampaignCanvas .jtk-endpoint *{cursor:crosshair}#CampaignCanvas .jtk-endpoint{z-index:1011!important}';

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

        $customContentEvent->addContent('<style>'.self::STYLE.'</style>');
        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-endpoints-outside.js')
            .';mauticPatchesEndpointsOutsideApi.install(Mautic);</script>'
        );
    }
}
