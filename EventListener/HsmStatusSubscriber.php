<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The same as UserStatusSubscriber, for the HSM template list of N8nDispatchBundle: the active / inactive toggle next to the name (an
 * icon that is easy to hit by mistake, with the status told only by its color) becomes
 *  - a label at the start of the name ("Ativo" green, "Inativo" red, informative, not a link, all with the width of "Inativo");
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit the template (the permissions of the SMS / HSM templates, `sms:smses`).
 * Core's flow is kept: the menu item clicks core's icon (hidden), so the server checks the permission as it always did.
 *
 * The list has no name hook: the script (Resources/js/segment-status-toggle.js, installHsm) makes the labels from the state of
 * core's icons. The template class is compared by name, so this bundle does not depend on N8nDispatchBundle.
 */
class HsmStatusSubscriber implements EventSubscriberInterface
{
    public const ENTITY = 'MauticPlugin\\N8nDispatchBundle\\Entity\\HsmTemplate';

    public const STYLE = '#hsmTemplateTable i[class*="n8ndispatch-hsmtemplate-publish-icon"]{display:none!important}'
        .'a[data-hsm-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-hsm-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-hsm-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-hsm-status-action]:hover *{color:inherit!important}'
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
            .';mauticPatchesSegmentStatusApi.installHsm(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
        );
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $template = $event->getItem();

        if (!\is_object($template)
            || self::ENTITY !== $template::class
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_n8ndispatch.hsmtemplate_index' !== $event->getRoute()
            || !$this->security->hasEntityAccess('sms:smses:editown', 'sms:smses:editother', $template->getCreatedBy())) {
            return;
        }

        $published = $template->isPublished();
        $event->addButton(
            [
                'attr' => [
                    // not 'ajax': core would add that and navigate; the script clicks core's icon
                    'data-toggle'            => 'hsm-status',
                    'data-hsm-id'            => $template->getId(),
                    'data-hsm-status-action'  => $published ? 'deactivate' : 'activate',
                    'href'                    => 'javascript:void(0);',
                ],
                'btnText'   => $this->translator->trans($published ? 'mautic.patches.segment.deactivate' : 'mautic.patches.segment.activate'),
                'iconClass' => $published ? 'ri-close-circle-line' : 'ri-checkbox-circle-line',
                'priority'  => 300, // first in the row menu: above Edit and, far below, Delete
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
