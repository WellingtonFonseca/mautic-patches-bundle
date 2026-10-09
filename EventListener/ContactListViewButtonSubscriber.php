<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Ver" to the three-dots menu of each row of the contact list. It opens, in core's shared modal (the same
 * ajaxmodal the Dispatches screen of the n8n plugin uses), the contact's Details table, so a contact can be
 * looked at without leaving the list. The table is rendered by ContactDetailsController. The button is first in
 * the menu (above Edit / Delete).
 */
class ContactListViewButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $contact = $event->getItem();

        if (!$contact instanceof Lead
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_contact_index' !== $event->getRoute()) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'data-toggle' => 'ajaxmodal',
                    'data-target' => '#MauticSharedModal',
                    'data-header' => $this->translator->trans('mautic.patches.contact.view.title'),
                    'href'        => $this->router->generate('mautic_patches_contact_details', ['id' => $contact->getId()]),
                ],
                'btnText'   => $this->translator->trans('mautic.patches.contact.view'),
                'iconClass' => 'ri-eye-line',
                'priority'  => 300,
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
