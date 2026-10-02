<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes the buttons at the bottom of a modal form (Save, Save & Close, Close,
 * on a plugin's settings, among others) use the whole width of the modal
 * instead of sitting at its right edge.
 *
 * Core moves a form's buttons into `.modal-footer .modal-form-buttons`
 * (CoreBundle 1a.content.js) and styles it as `display:flex;
 * justify-content:flex-end` with every `.btn` a fixed `width:
 * var(--spacing-13)`; only a lone button grows (`:only-child{flex-grow:1}`).
 * With two or more they stay small and pushed to the right. Here each button
 * gets `flex: 1 1 0; width: auto`, so they share the footer equally; the
 * footer's own layout, the 1px gap and the rounded outer corners of the first
 * and last button stay core's.
 *
 * Injected the same way as DarkThemeTableSubscriber (CoreEvents::
 * VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', rendered on every admin
 * page). The :root prefix makes this rule more specific than core's, so the
 * order of the stylesheets does not matter.
 */
class ModalFormButtonsSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<style>:root .modal-footer .modal-form-buttons .btn{flex:1 1 0;width:auto}</style>');
    }
}
