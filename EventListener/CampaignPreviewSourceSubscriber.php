<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use MauticPlugin\MauticPatchesBundle\Service\SegmentSourceNames;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Preview tab of the campaign page draws the contact source with the names of the segments straight from the
 * entities (core's Campaign/_preview.html.twig), so CampaignPrefixModel does not reach it. Core's template is not
 * touched: on 'tabs.content' of the campaign page (the hook the page already has, after the Preview tab) a script
 * rewrites the text of the source node from "Name, Name" to "PREFIX - Name, Name". The old text is the names joined
 * the way core joins them, so only that exact text is replaced.
 */
class CampaignPreviewSourceSubscriber implements EventSubscriberInterface
{
    public function __construct(private SegmentPrefixStore $store)
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
        if ('tabs.content' !== $customContentEvent->getContext()) {
            return;
        }

        $campaign = $customContentEvent->getVars()['campaign'] ?? null;
        if (!$campaign instanceof Campaign) {
            return;
        }

        $names = [];
        foreach ($campaign->getLists() as $segment) {
            $names[$segment->getId()] = (string) $segment->getName();
        }

        // Not sorted: the Preview tab lists them in the order of the campaign.
        $prefixes = $this->store->all();
        $shown    = [];
        foreach ($names as $id => $name) {
            $shown[$id] = SegmentSourceNames::label($name, $prefixes[(int) $id] ?? '');
        }

        $old = implode(', ', $names);
        $new = implode(', ', $shown);
        if ($old === $new) {
            return;
        }

        $customContentEvent->addContent('<script>'.self::script($old, $new).'</script>');
    }

    public static function script(string $old, string $new): string
    {
        return '(function(){'
            .'function fix(){'
            .'var el=document.querySelector("#preview-container #CampaignEvent_lists .campaign-event-name");'
            .'if(!el){return;}'
            .'for(var n=el.firstChild;n;n=n.nextSibling){'
            .'if(n.nodeType===3&&n.nodeValue.trim()==='.json_encode($old, JSON_UNESCAPED_UNICODE).'){'
            .'n.nodeValue=n.nodeValue.replace('.json_encode($old, JSON_UNESCAPED_UNICODE).','.json_encode($new, JSON_UNESCAPED_UNICODE).');'
            .'}}}'
            .'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fix);}else{fix();}'
            .'})();';
    }
}
