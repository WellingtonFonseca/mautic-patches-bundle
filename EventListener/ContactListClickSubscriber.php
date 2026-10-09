<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Two changes to where a contact in the contact list takes the user, to save trips between screens:
 *  - a click on the contact's NAME opens the contact's Details table in core's shared modal (the body is rendered by
 *    ContactDetailsController) instead of leaving the list for the contact page;
 *  - the "Detalhes" option of the row's three-dots menu (the contact page) opens in a NEW TAB instead of replacing
 *    the list. A ctrl/cmd/shift click on the name does the same, as it does on any link.
 *
 * Core's templates are not touched: a click handler in the capture phase (before core's own ajax link handler, which
 * it then stops) looks at links to /s/contacts/view/{id} inside the contact list table (#leadTable) only. The modal is
 * opened with core's own Mautic.ajaxifyModal, from a link built on the fly with the same attributes the Dispatches
 * modal of the n8n plugin uses. Other links, other tables and the grid view are not affected.
 *
 * Injected the same way as SegmentUpdateLockSubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class ContactListClickSubscriber implements EventSubscriberInterface
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

        // The route with a placeholder id of 0: the script puts the contact's id after the base.
        $base = (string) preg_replace('#0$#', '', $this->router->generate('mautic_patches_contact_details', ['id' => 0]));

        $customContentEvent->addContent('<script>'.self::script($base, $this->translator->trans('mautic.patches.contact.view.title')).'</script>');
    }

    public static function script(string $detailsBase, string $header): string
    {
        return '(function(){'
            .'if(window.mauticPatchesContactClicks){return;}'
            .'window.mauticPatchesContactClicks=true;'
            .'var re=/^\/s\/contacts\/view\/(\d+)(?:[?#].*)?$/;'
            // Capture phase: runs before core's ajax link handler, so the click never reaches it.
            .'document.addEventListener("click",function(event){'
            .'var a=event.target.closest?event.target.closest("#leadTable a[href]"):null;'
            .'if(!a||event.button){return;}'
            .'var m=re.exec(a.getAttribute("href")||"");if(!m){return;}'
            .'event.preventDefault();event.stopImmediatePropagation();'
            // The menu option and a modified click go to the contact page in a new tab.
            .'if(a.closest(".page-list-actions")||event.ctrlKey||event.metaKey||event.shiftKey){'
            .'window.open(a.getAttribute("href"),"_blank","noopener");return;}'
            // The name opens the Details table in the shared modal.
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
