<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Core rounds the top-right corner of the filter input (its blue focus border too) when the magnifier is the ONLY button of the list
 * toolbar: `.list-toolbar > .btn:only-of-type + .input-group > input`. A list with the funnel button (Segments) has two buttons, so the rule
 * misses and the input stays square. The lists without a funnel (Custom Fields, Custom Objects, WhatsApp messages and numbers) showed the
 * rounded input next to the gear. The same selector, later in the page, squares it.
 * The gear menu opens to the left for the same screens. Twin of the rules in the Custom Objects plugin (Assets/css/custom-objects.css): plugins stay independent.
 */
class ListToolbarSquareSearchSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => 'injectAssets'];
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
        return '.list-toolbar > .btn:only-of-type + .input-group > input{border-radius:0}'
            // the gear menu sits at the right edge: opening to the left keeps long items (Histórico de importação) on screen
            .'.list-toolbar .dropdown > .dropdown-menu{left:auto;right:0}';
    }
}
