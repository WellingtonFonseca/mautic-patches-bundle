<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The confirmation modal of core (delete a contact, segment, campaign, email; deactivate a campaign; cancel a scheduled event; ...) has its
 * buttons in the body, with Cancel as the primary. Here they go to the modal footer like every other modal of the project: the action
 * first (as core draws it, btn-danger for a delete), Cancel last (btn-tertiary btn-cancel). Resources/js/confirmation-footer.js wraps core's
 * Mautic.showConfirmation, so every confirmation is covered and core's click handlers are kept.
 *
 * Core also pads the confirmation's `.modal-content` by 8px (custom.less), so the footer, whose buttons reach the modal's edges through the
 * `margin:-15px` of `.modal-form-buttons`, would stay 8px short of them: the padding goes (the header keeps its own) and the corners are the large radius of the other modals (core's 8px did not follow the footer buttons' corners) and the red action's text and icon are white in the light themes and black in the dark ones, like the Delete item of the row menus, the footer's padding is the 15px its buttons' margin cancels (core's 16px left a 1px edge), its text is
 * left aligned and regular weight like the other modals' footers.
 *
 * Injected the same way as FlashesLongerSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class ConfirmationFooterSubscriber implements EventSubscriberInterface
{
    /** The text and icon of the red action: white in the light themes, black in the dark ones, as the Delete item of the row menus (MenuDeleteHoverSubscriber). */
    public static function deleteColors(): string
    {
        $css = ':root .confirmation-modal .modal-footer .btn-danger,:root .confirmation-modal .modal-footer .btn-danger i{color:#fff}';
        foreach (MenuDeleteHoverSubscriber::DARK_THEMES as $theme) {
            $root = ':root[theme="'.$theme.'"] .confirmation-modal .modal-footer .btn-danger';
            $css .= $root.','.$root.' i{color:#000}';
        }

        return $css;
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

        $customContentEvent->addContent(
            '<style>:root .confirmation-modal .modal-content{padding:0;overflow:hidden;border-radius:var(--border-radius-lg)}'
            .':root .confirmation-modal .modal-footer{padding:15px;text-align:left;font-weight:normal}'
            .self::deleteColors().'</style>'
            .'<script>'.file_get_contents(__DIR__.'/../Resources/js/confirmation-footer.js')
            .';mauticPatchesConfirmationFooterApi.install(mQuery,Mautic);</script>'
        );
    }
}
