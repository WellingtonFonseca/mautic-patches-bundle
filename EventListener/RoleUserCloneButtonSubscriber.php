<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The export opens in the shared modal from three places of the roles list and of the users list (administrators only): "Exportar todos"
 * in the page actions (with "Importar"), "Exportar" in the row menu (that item alone; a user brings its role), and "Exportar
 * selecionados" in the bulk menu (the checked rows).
 */
class RoleUserCloneButtonSubscriber implements EventSubscriberInterface
{
    private const KINDS = ['mautic_role_index' => 'roles', 'mautic_user_index' => 'users'];

    /** The table whose check boxes the bulk action reads (core's list templates). */
    private const TABLES = ['roles' => '#roleTable', 'users' => '#userTable'];

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
        $kind     = self::KINDS[$event->getRoute()] ?? null;
        $location = $event->getLocation();
        if (null === $kind || !$this->security->isAdmin()) {
            return;
        }

        if (ButtonHelper::LOCATION_PAGE_ACTIONS === $location) {
            $event->addButton($this->exportButton($kind, 'export_all', [], 200), $location);
            $event->addButton($this->importButton($kind), $location);
        }
        if (ButtonHelper::LOCATION_BULK_ACTIONS === $location) {
            $event->addButton($this->selectedButton($kind), $location);
        }
        $item = $event->getItem();
        if (ButtonHelper::LOCATION_LIST_ACTIONS === $location && ($item instanceof Role || $item instanceof User)) {
            $event->addButton($this->exportButton($kind, 'export_one', ['ids' => '['.$item->getId().']'], 200), $location);
        }
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function exportButton(string $kind, string $textKey, array $query, int $priority): array
    {
        return [
            'attr' => [
                'data-toggle' => 'ajaxmodal',
                'data-target' => '#MauticSharedModal',
                'data-header' => $this->translator->trans('mautic.patches.role_user_clone.export.title.'.$kind),
                'href'        => $this->router->generate('mautic_patches_role_user_clone_export', ['kind' => $kind] + $query),
            ],
            'btnText'   => $this->translator->trans('mautic.patches.role_user_clone.button.'.$textKey.'.'.$kind),
            'iconClass' => 'ri-download-2-line',
            'priority'  => $priority,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function importButton(string $kind): array
    {
        return [
            'attr'      => ['data-toggle' => 'ajax', 'href' => $this->router->generate('mautic_patches_role_user_clone_import', ['kind' => $kind])],
            'btnText'   => $this->translator->trans('mautic.patches.role_user_clone.button.import.'.$kind),
            'iconClass' => 'ri-upload-2-line',
            'priority'  => 190,
        ];
    }

    /**
     * The export modal with the ids of the checked rows. The href is completed on click (before core's modal handler reads it), because
     * the checks change after the menu is drawn. The menu stays disabled until a row is checked, so the ids are never empty here.
     * Core's getCheckedListIds is not used: it reads the table from data-target, which the modal needs for itself.
     *
     * @return array<string, mixed>
     */
    private function selectedButton(string $kind): array
    {
        $button                      = $this->exportButton($kind, 'export_selected', [], 200);
        $button['attr']['data-base'] = $button['attr']['href'];
        $button['attr']['onclick']   = "this.href = this.getAttribute('data-base') + '?ids=' + encodeURIComponent(JSON.stringify(mQuery('".self::TABLES[$kind]." input[class=list-checkbox]:checked').map(function () { return mQuery(this).val(); }).get()));";

        return $button;
    }
}
