<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The notifications of Mautic (the faint at the top right: "Segmento salvo", "Este segmento está sendo usado na campanha...") are in
 * `#flashes.alert-growl-container`, which core positions `absolute` (top 20px, right 20px): they belong to the PAGE, so on a long list that
 * was scrolled they are up at the top, out of sight. The container is `fixed` instead: they belong to the SCREEN, always in the corner of
 * what is in view. The top, right and z-index of core stay.
 *
 * Injected the same way as ModalFormButtonsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on every
 * admin page). The :root prefix makes the rule more specific than core's, so the order of the stylesheets does not matter.
 */
class FlashesFixedSubscriber implements EventSubscriberInterface
{
    public const STYLE = ':root .alert-growl-container{position:fixed}';

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
    }
}
