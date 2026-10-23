<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The icon of the Wait node (patches.wait_relative): the pause. Core picks the icon of a node in getCampaignEventIcon(), a Twig function of
 * a FINAL extension with a fixed map and `ri-shapes-fill` as the default, used by the builder (Event/_generic.html.twig) and by the Preview
 * tab (Event/_preview.html.twig): it cannot be extended. Both templates put the type of the event in the node's `data-event`, so one CSS
 * rule gives that node the pause glyph in both places: it only replaces the `content` of the icon's :before (the font and the size are
 * core's). The glyph is Remix Icon's `ri-pause-fill` (\efd7, the same on Mautic 5 and 7); a Remix Icon upgrade that renumbers
 * the glyphs would need the code changed here (the browser spec reads the glyph of the node).
 *
 * Injected the same way as CampaignEndpointsOnTopSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class RelativeWaitIconSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<style>[data-event="'.RelativeWaitSubscriber::TYPE.'"] .campaign-event-icon i.ri-shapes-fill:before{content:"\efd7"}</style>');
    }
}
