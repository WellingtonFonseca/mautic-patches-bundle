<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A usability change: the node buttons of the campaign builder (Clonar, Editar, Remover) become icon badges that open their label on hover,
 * like the disconnect badge. The buttons stay core's (same handlers and colors). See Resources/js/campaign-node-button-badges.js for how.
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class CampaignNodeButtonBadgeSubscriber implements EventSubscriberInterface
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

        $labels = [
            'clone'  => $this->translator->trans('mautic.patches.campaign.node.clone'),
            'edit'   => $this->translator->trans('mautic.patches.campaign.node.edit'),
            'remove' => $this->translator->trans('mautic.patches.campaign.node.remove'),
        ];

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/campaign-node-button-badges.js')
            .';if(!window.mauticPatchesNodeButtonBadges){mauticPatchesNodeButtonBadgesApi.install(window,'.json_encode($labels, JSON_UNESCAPED_UNICODE).');window.mauticPatchesNodeButtonBadges=true;}</script>'
        );
    }
}
