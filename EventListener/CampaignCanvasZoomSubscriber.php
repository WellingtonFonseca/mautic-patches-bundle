<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A usability change, not a bug: core's campaign builder has no zoom, so a large campaign cannot be seen as a whole.
 * Four buttons below Save and Close (out, % that resets, in, fit all nodes), CTRL + wheel and CTRL + / - / 0 scale the canvas. See Resources/js/campaign-canvas-zoom.js for how.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignCanvasZoomSubscriber implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator)
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

        $config = [
            'zoomIn' => $this->translator->trans('mautic.patches.campaign.zoom_in'),
            'zoomOut' => $this->translator->trans('mautic.patches.campaign.zoom_out'),
            'zoomFit' => $this->translator->trans('mautic.patches.campaign.zoom_fit'),
            'zoomReset' => $this->translator->trans('mautic.patches.campaign.zoom_reset'),
        ];

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-canvas-zoom.js')
            .';mauticPatchesCanvasZoomApi.install(Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
        );
    }
}
