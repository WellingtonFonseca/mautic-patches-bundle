<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The title of the campaign page ("PREFIX - Name"), in the page header and in the browser tab. Core's details
 * template prints the name straight from the entity (block headerTitle) and is not touched: on 'page.header.left'
 * (the hook of the page header, where the other page scripts of this plugin go) a script rewrites the heading and
 * the document title. The name of the campaign itself is not changed.
 */
class CampaignTitlePrefixSubscriber implements EventSubscriberInterface
{
    public function __construct(private CampaignPrefixStore $store)
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

        $campaign = $customContentEvent->getVars()['campaign'] ?? null;
        if (!$campaign instanceof Campaign) {
            return;
        }

        $prefix = $this->store->get($campaign->getId());
        if (null === $prefix || '' === $prefix) {
            return;
        }

        $name = (string) $campaign->getName();
        $customContentEvent->addContent('<script>'.self::script($name, $prefix.' - '.$name).'</script>');
    }

    public static function script(string $old, string $new): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
        $old   = json_encode($old, $flags);
        $new   = json_encode($new, $flags);

        return '(function(){'
            .'function fix(){'
            .'var h=document.querySelector("h1.page-header-title");'
            .'if(!h||h.textContent.trim()!=='.$old.'){return;}'
            .'h.textContent='.$new.';'
            .'if(document.title.indexOf('.$new.')===-1){document.title=document.title.replace('.$old.','.$new.');}'
            .'}'
            .'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fix);}else{fix();}'
            .'})();';
    }
}
