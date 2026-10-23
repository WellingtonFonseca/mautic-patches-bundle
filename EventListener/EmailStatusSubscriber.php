<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\EmailBundle\Entity\Email;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The same as HsmStatusSubscriber, for the email list: the active / inactive toggle next to the name (an icon that is easy to hit by
 * mistake, with the status told only by its color) becomes
 *  - a label at the start of the name ("Ativo" green, "Inativo" red, informative, not a link, all with the width of "Inativo");
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit the email.
 * Core's flow is kept: the menu item clicks core's icon (hidden), so the server checks the permission as it always did. An email
 * published but outside its publish dates (core's icon says pending / expired) counts as active, like its `isPublished` flag.
 *
 * The list has no hook that fits (the one at the name is inside the link): the script (Resources/js/segment-status-toggle.js,
 * installEmail) makes the labels from the state of core's icons.
 */
class EmailStatusSubscriber implements EventSubscriberInterface
{
    public const STYLE = 'table.email-list i[class*="email-publish-icon"]{display:none!important}'
        .'a[data-email-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-email-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-email-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-email-status-action]:hover *{color:inherit!important}'
        // the label's own style (width, margin, hidden until placed) is SegmentStatusSubscriber::STYLE, on every page
        ;

    public function __construct(
        private TranslatorInterface $translator,
        private CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $config = [
            'active'     => $this->translator->trans('mautic.core.form.active'),
            'inactive'   => $this->translator->trans('mautic.core.form.inactive'),
            'activate'   => $this->translator->trans('mautic.patches.segment.activate'),
            'deactivate' => $this->translator->trans('mautic.patches.segment.deactivate'),
            'style'      => self::STYLE,
        ];
        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-status-toggle.js')
            .';mauticPatchesSegmentStatusApi.installEmail(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
        );
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $email = $event->getItem();

        if (!$email instanceof Email
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_email_index' !== $event->getRoute()
            || !$this->security->hasEntityAccess('email:emails:editown', 'email:emails:editother', $email->getCreatedBy())) {
            return;
        }

        $published = $email->isPublished();
        $event->addButton(
            [
                'attr' => [
                    // not 'ajax': core would add that and navigate; the script clicks core's icon
                    'data-toggle'              => 'email-status',
                    'data-email-id'            => $email->getId(),
                    'data-email-status-action' => $published ? 'deactivate' : 'activate',
                    'href'                     => 'javascript:void(0);',
                ],
                'btnText'   => $this->translator->trans($published ? 'mautic.patches.segment.deactivate' : 'mautic.patches.segment.activate'),
                'iconClass' => $published ? 'ri-close-circle-line' : 'ri-checkbox-circle-line',
                'priority'  => 300, // first in the row menu: above Edit and, far below, Delete
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
