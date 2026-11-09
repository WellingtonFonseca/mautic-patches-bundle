<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Preferências" to the three-dots menu of each row of the contact list. It opens core's own preferences modal
 * (the one of the contact page: channels on / off, frequency, preferred channel) in the shared modal, with the same
 * URL, header and label as core's button, so the form and its save are core's. Only for who may edit the contact,
 * like core's button. Only the contact list itself: the contact tables of a segment or campaign have another route.
 */
class ContactPreferencesButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0]];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $contact = $event->getItem();

        if (ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_contact_index' !== $event->getRoute()
            || !$contact instanceof Lead
            || !$this->security->hasEntityAccess('lead:leads:editown', 'lead:leads:editother', $contact->getPermissionUser())) {
            return;
        }

        $event->addButton([
            'attr' => [
                'data-toggle' => 'ajaxmodal',
                'data-target' => '#MauticSharedModal',
                'data-header' => $this->translator->trans('mautic.lead.lead.header.contact.frequency', ['%name%' => $contact->getPrimaryIdentifier()]),
                'href'        => $this->router->generate('mautic_contact_action', ['objectId' => $contact->getId(), 'objectAction' => 'contactFrequency']),
            ],
            'btnText'   => $this->translator->trans('mautic.lead.contact.frequency'),
            'iconClass' => 'ri-settings-5-line',
            'priority'  => 140,
        ], ButtonHelper::LOCATION_LIST_ACTIONS);
    }
}
