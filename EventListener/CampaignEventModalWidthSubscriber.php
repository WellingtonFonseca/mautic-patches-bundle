<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Widens the modal of the campaign builder where an event (an action, a condition or a decision) is configured.
 *
 * Core opens every event form in `#CampaignEventModal` (CampaignBundle Campaign/_builder.html.twig) at the default
 * width of a modal, which is narrow for forms with several controls side by side, such as the Custom Objects condition
 * (field, operator, value, one row per criterion). The same size as the contact details modal (1100px), never wider
 * than the screen. Only that modal: every other modal keeps its size.
 *
 * Injected the same way as ModalFormButtonsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 * The `:root` prefix makes the rule more specific than core's `.modal-dialog` width, so the order of the stylesheets
 * does not matter.
 */
class CampaignEventModalWidthSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<style>:root #CampaignEventModal .modal-dialog{width:1100px;max-width:95vw}</style>');
    }
}
