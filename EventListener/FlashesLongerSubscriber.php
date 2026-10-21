<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The notifications of Mautic go away after 4 seconds, too soon to be noticed and read (mostly the error ones, like "Este segmento está sendo
 * usado na campanha ..."). They stay 10 s a notice, 15 s a warning and 20 s an error, and while the mouse is over one it stays (it goes 4 s
 * after the mouse leaves). The close button still closes at once. Resources/js/flashes-longer.js wraps core's Mautic.setFlashes.
 *
 * Injected the same way as SegmentFilterDuplicateSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class FlashesLongerSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/flashes-longer.js')
            .';mauticPatchesFlashesLongerApi.install(mQuery,Mautic);</script>'
        );
    }
}
