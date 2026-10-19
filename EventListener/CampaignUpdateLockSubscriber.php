<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The same lock as the segment's (SegmentUpdateLockSubscriber::script, kind
 * 'campaign') for the campaign "Update" button: after a click the button of THAT
 * campaign is disabled and its line in the list becomes "<spinner> Updating..."
 * until the status route says the update is no longer running; then the list (or
 * the campaign's page) is reloaded. The style is the segment's one, so both look alike.
 */
class CampaignUpdateLockSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<script>'.SegmentUpdateLockSubscriber::script(SegmentUpdateLockSubscriber::POLL_MS, SegmentUpdateLockSubscriber::MAX_WAIT_MS, 'campaign').'</script>');
        $customContentEvent->addContent('<style>'.SegmentUpdateLockSubscriber::STYLE.'</style>');
    }
}
