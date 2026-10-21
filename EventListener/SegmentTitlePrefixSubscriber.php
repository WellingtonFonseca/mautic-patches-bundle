<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The title of the segment page ("PREFIX - Name"), in the page header and in the browser tab, like the campaign page
 * (CampaignTitlePrefixSubscriber, whose script this one uses). Core's details template prints the name straight from the entity
 * (block headerTitle) and is not touched: on 'page.header.left' a script rewrites the heading and the document title. The name of
 * the segment itself is not changed.
 */
class SegmentTitlePrefixSubscriber implements EventSubscriberInterface
{
    public function __construct(private SegmentPrefixStore $store)
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

        // the details template calls it with its own variables: the segment is `list`
        $segment = $customContentEvent->getVars()['list'] ?? null;
        if (!$segment instanceof LeadList || null === $segment->getId()) {
            return;
        }

        $prefix = $this->store->get($segment->getId());
        if (null === $prefix || '' === $prefix) {
            return;
        }

        $name = (string) $segment->getName();
        $customContentEvent->addContent('<script>'.CampaignTitlePrefixSubscriber::script($name, $prefix.' - '.$name).'</script>');
    }
}
