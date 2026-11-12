<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\StatsBlockScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A layout choice for the email page: the Contagem de cliques / Contatos tabs come first and Estatísticas / Mapa goes below them.
 * Core's template is not touched: a script moves the block (Service/StatsBlockScript, the same one as the campaign page). Why it
 * is documented in wiki/patches-email-page.md.
 */
class EmailViewOrderSubscriber implements EventSubscriberInterface
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

        $customContentEvent->addContent('<script>'.self::script().'</script>');
    }

    public static function script(): string
    {
        return StatsBlockScript::after('mauticPatchesEmailViewOrder', '#clicks-container', 'tabs');
    }
}
