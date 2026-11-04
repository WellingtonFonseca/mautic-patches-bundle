<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Exportar definição" and "Importar definição" to the page actions menu of the contact fields list, for who manages fields.
 */
class FieldDefinitionButtonSubscriber implements EventSubscriberInterface
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
            || 'mautic_contactfield_index' !== $event->getRoute()
            || !$this->security->isGranted('lead:fields:full')) {
            return;
        }

        $event->addButton($this->button('mautic_patches_field_definition_export', 'export', 'ri-download-2-line', 200), ButtonHelper::LOCATION_PAGE_ACTIONS);
        $event->addButton($this->button('mautic_patches_field_definition_import', 'import', 'ri-upload-2-line', 190), ButtonHelper::LOCATION_PAGE_ACTIONS);
    }

    /**
     * @return array<string, mixed>
     */
    private function button(string $route, string $key, string $icon, int $priority): array
    {
        return [
            'attr'      => ['data-toggle' => 'ajax', 'href' => $this->router->generate($route)],
            'btnText'   => $this->translator->trans('mautic.patches.field_definition.button.'.$key),
            'iconClass' => $icon,
            'priority'  => $priority,
        ];
    }
}
