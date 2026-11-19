<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The name of a Wait node has four lines separated by "\n": the canvas shows them with `white-space: pre-line` and without the
 * single-line ellipsis core puts on the name. Only the Wait node, and only in the admin (/s/), like SidebarRevealOnHoverSubscriber.
 */
class RelativeWaitCanvasStyleSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => 'injectAssets',
        ];
    }

    public function injectAssets(CustomAssetsEvent $assetsEvent): void
    {
        if (!str_starts_with((string) $this->requestStack->getMainRequest()?->getPathInfo(), '/s/')) {
            return;
        }

        $assetsEvent->addStyleDeclaration(self::style());
    }

    public static function style(): string
    {
        return '.list-campaign-event[data-event="'.RelativeWaitSubscriber::TYPE.'"] .campaign-event-name'
            .'{white-space: pre-line;overflow:visible;text-overflow:clip;height:auto;max-height:none}';
    }
}
