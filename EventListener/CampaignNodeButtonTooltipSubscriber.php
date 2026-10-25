<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A bug fix by removal: the node buttons of the campaign builder (Clonar, Editar, Remover) have no tooltip. Core's tooltip of a button
 * stays on the canvas, over the buttons of the nodes around, when the button goes away under the mouse (the node was removed, the
 * buttons were hidden when the edit modal opened). See Resources/js/campaign-node-button-tooltips.js for how.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignNodeButtonTooltipSubscriber implements EventSubscriberInterface
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
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-node-button-tooltips.js')
            .';if(!window.mauticPatchesNodeButtonTooltips){mauticPatchesNodeButtonTooltipsApi.install(window);window.mauticPatchesNodeButtonTooltips=true;}</script>'
        );
    }
}
