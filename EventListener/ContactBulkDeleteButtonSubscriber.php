<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\Controller\ContactBulkDeleteController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Excluir em lote" (Delete in bulk) to the page actions menu (the gear) of the contact list in red like core's delete items
 * (`a.danger`), for who may delete other users' contacts.
 */
class ContactBulkDeleteButtonSubscriber implements EventSubscriberInterface
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
        if (ButtonHelper::LOCATION_PAGE_ACTIONS !== $event->getLocation()
            || 'mautic_contact_index' !== $event->getRoute()
            || !$this->security->isGranted(ContactBulkDeleteController::PERMISSION)) {
            return;
        }

        $text = $this->translator->trans('mautic.patches.contact_bulk_delete.menu');

        $event->addButton([
            'attr'      => [
                'data-toggle' => 'ajax',
                'href'        => $this->router->generate('mautic_patches_contact_bulk_delete'),
                'class'       => 'danger',
            ],
            'btnText'   => $text,
            'iconClass' => 'ri-delete-bin-line',
            'priority'  => -100,
        ], ButtonHelper::LOCATION_PAGE_ACTIONS);
    }
}
