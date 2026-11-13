<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The counts of EVERY node of the campaign Preview (`.campaign-event-stat`, core's Event/_preview.html.twig) sit apart from the text, at
 * the right end of the node: core puts them right after the text, so a long line touched them. The padding needs `!important` because
 * core's utility `pa-4` (padding 4px) on the counts is `!important`; `margin-left:auto` only moves them when the node has room to spare.
 * The counts open nothing: the contacts of a step are listed on the "Etapas" tab (CampaignStepsTabSubscriber).
 */
class CampaignNodeStatStyleSubscriber implements EventSubscriberInterface
{
    public const STAT_STYLE = '.campaign-event-stat{margin-left:auto;padding-left:24px!important}';

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

        $customContentEvent->addContent('<style>'.self::STAT_STYLE.'</style>');
    }
}
