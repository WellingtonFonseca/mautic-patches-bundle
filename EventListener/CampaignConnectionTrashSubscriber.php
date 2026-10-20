<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A usability change, not a bug: a trash button on every line of the campaign builder, shown when the mouse is over the line,
 * to disconnect it with a click. Core only disconnects by grabbing the half circle of the node and dragging the line away.
 * See Resources/js/campaign-connection-trash.js for how.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignConnectionTrashSubscriber implements EventSubscriberInterface
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

        $config = ['title' => $this->translator->trans('mautic.patches.campaign.disconnect')];

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-connection-trash.js')
            .';mauticPatchesConnectionTrashApi.install(Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
        );
    }
}
