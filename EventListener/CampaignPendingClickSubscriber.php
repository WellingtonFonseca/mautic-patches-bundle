<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A click on the hourglass of a campaign node (the pending count; only the Preview tab of the campaign page has it, the builder shows no counts) opens, in core's
 * shared modal (#MauticSharedModal, Mautic.ajaxifyModal), the contacts waiting at that node (the body is rendered by
 * CampaignEventPendingController, the table by CampaignPendingContactsController, loaded by Mautic.campaignPendingOnLoad). The check next to it (the done count) is not touched.
 *
 * Core's template (Event/_preview.html.twig, the node) is not touched: a click handler in the capture phase, before core's own
 * handlers, looks at the stat span that holds `.ri-hourglass-line` or `.ri-check-line`; the node's id
 * is the data-event-id of the node it sits in. Same technique as ContactListClickSubscriber and injected the same way
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left'). Both get the pointer cursor from a style.
 */
class CampaignPendingClickSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
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

        // The route with a placeholder id of 0: the script puts the event's id after the base.
        $pending = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_campaign_event_pending', ['eventId' => 0]));
        $done    = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_campaign_event_done', ['eventId' => 0]));

        $customContentEvent->addContent(
            '<style>.campaign-event-stat > span[data-toggle]:has(.ri-hourglass-line),.campaign-event-stat > span[data-toggle]:has(.ri-check-line){cursor:pointer}</style>'
            .'<script>'.self::script(
                $pending,
                $this->translator->trans('mautic.patches.campaign.pending.title'),
                $done,
                $this->translator->trans('mautic.patches.campaign.done.title')
            ).'</script>'
        );
    }

    public static function script(string $pendingBase, string $pendingHeader, string $doneBase, string $doneHeader): string
    {
        return '(function(){'
            .'if(window.mauticPatchesCampaignPendingClicks){return;}'
            .'window.mauticPatchesCampaignPendingClicks=true;'
            // Capture phase: runs before core's handlers, so the click never reaches them.
            .'document.addEventListener("click",function(event){'
            .'var span=event.target&&event.target.closest?event.target.closest(".campaign-event-stat > span[data-toggle]"):null;'
            .'if(!span||event.button){return;}'
            // The hourglass lists who waits at the node, the check who finished it.
            .'var pending=span.querySelector(".ri-hourglass-line"),done=pending?null:span.querySelector(".ri-check-line");'
            .'if(!pending&&!done){return;}'
            .'var node=span.closest("[data-event-id]"),id=node?node.getAttribute("data-event-id"):null;'
            .'if(!id){return;}'
            .'event.preventDefault();event.stopImmediatePropagation();'
            .'var el=document.createElement("a");'
            .'el.setAttribute("href",(pending?'.json_encode($pendingBase, JSON_UNESCAPED_SLASHES).':'.json_encode($doneBase, JSON_UNESCAPED_SLASHES).')+id);'
            .'el.setAttribute("data-toggle","ajaxmodal");'
            .'el.setAttribute("data-target","#MauticSharedModal");'
            .'el.setAttribute("data-header",pending?'.json_encode($pendingHeader, JSON_UNESCAPED_UNICODE).':'.json_encode($doneHeader, JSON_UNESCAPED_UNICODE).');'
            .'Mautic.ajaxifyModal(el,event);'
            .'},true);'
            // The modal's body only has the container: its table is loaded when the body arrives (core calls <mauticContent>OnLoad).
            .'Mautic.campaignPendingOnLoad=function(){'
            .'var url=mQuery("#patches-pending-container").data("target-url");'
            .'if(url){mQuery.get(url,function(response){response.target="#patches-pending-container";Mautic.processPageContent(response);});}'
            .'};'
            .'})();';
    }
}
