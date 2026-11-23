<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A click on a campaign's NAME in the campaign list opens, in core's shared modal (#MauticSharedModal, Mautic.ajaxifyModal), the header
 * and the tabs of the campaign page (Preview, Etapas; the body is rendered by CampaignDetailsController) instead of leaving the list. The page stays
 * one click away: the "Details" option of the row menu (CampaignDetailsButtonSubscriber) and the Details button of the modal. Same technique and
 * same exceptions as SegmentListClickSubscriber (capture-phase handler; ctrl/cmd/shift, the middle button and the row menu's links are left alone).
 */
class CampaignListClickSubscriber implements EventSubscriberInterface
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

        // The route with a placeholder id of 0: the script puts the campaign's id after the base.
        $base = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_campaign_details', ['id' => 0]));

        $customContentEvent->addContent('<script>'.self::script($base, $this->translator->trans('mautic.patches.campaign.details.title')).'</script>');
    }

    public static function script(string $detailsBase, string $header): string
    {
        return '(function(){'
            .'if(window.mauticPatchesCampaignListClicks){return;}'
            .'window.mauticPatchesCampaignListClicks=true;'
            .'var re=/^\/s\/campaigns\/view\/(\d+)(?:[?#].*)?$/;'
            .'var listRe=/^\/s\/campaigns(?:\/\d+)?$/;'
            .'document.addEventListener("click",function(event){'
            .'var a=event.target.closest?event.target.closest("#page-list-wrapper a[href]"):null;'
            .'if(!a||event.button||event.ctrlKey||event.metaKey||event.shiftKey){return;}'
            .'if(!listRe.test(window.location.pathname)||a.closest(".page-list-actions")){return;}'
            .'var m=re.exec(a.getAttribute("href")||"");if(!m){return;}'
            .'event.preventDefault();event.stopImmediatePropagation();'
            .'var el=document.createElement("a");'
            .'el.setAttribute("href",'.json_encode($detailsBase, JSON_UNESCAPED_SLASHES).'+m[1]);'
            .'el.setAttribute("data-toggle","ajaxmodal");'
            .'el.setAttribute("data-target","#MauticSharedModal");'
            .'el.setAttribute("data-header",'.json_encode($header, JSON_UNESCAPED_UNICODE).');'
            .'Mautic.ajaxifyModal(el,event);'
            .'},true);'
            .'})();';
    }
}
