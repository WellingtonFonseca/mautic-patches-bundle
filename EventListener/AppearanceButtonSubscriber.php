<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds a sun / moon button left of the notification bell. It opens, in core's shared modal, the Appearance screen of the account
 * (the theme cards) so the theme changes from any page without going to the profile. The button and the choice are in
 * Resources/js/appearance-button.js; the modal body and the save of the choice are AppearanceModalController. The title and the button label reuse core's
 * 'Appearance' string.
 *
 * Injected the same way as ConfirmationFooterSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class AppearanceButtonSubscriber implements EventSubscriberInterface
{
    /**
     * The account page already has these radios (same ids and names as the modal's): with both on screen a click could reach the wrong
     * one. Mautic loads pages by ajax and keeps the navbar, so the rule follows the page content (:has) instead of the URL.
     */
    public const HIDE_ON_ACCOUNT_PAGE = 'body:has(#appearance.tab-pane) .patches-appearance-button{display:none}';

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

        $config = [
            'url'     => $this->router->generate('mautic_patches_appearance_modal'),
            'saveUrl' => $this->router->generate('mautic_patches_appearance_save'),
            'label'   => $this->translator->trans('mautic.user.account.appearance'),
        ];

        $customContentEvent->addContent(
            '<style>'.self::HIDE_ON_ACCOUNT_PAGE.'</style>'
            .'<script>'.file_get_contents(__DIR__.'/../Resources/js/appearance-button.js')
            .';mauticPatchesAppearanceApi.install(mQuery,Mautic,document,'
            .json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG).');</script>'
        );
    }
}
