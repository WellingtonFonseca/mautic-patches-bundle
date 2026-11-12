<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\Exception\ClonePackageException;
use MauticPlugin\MauticPatchesBundle\Form\Type\RoleUserCloneImportType;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserCloneAlerts;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserExporter;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserImporter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clone of the roles or of the users to another instance (the {kind} of the route: roles | users): the modal that shows the encrypted
 * package with its key (what to export is chosen in the core list: its gear, a row, or the checked rows), and the import (a check first, then the confirmation).
 * Only for administrators: the package holds password hashes.
 */
class RoleUserCloneController extends CommonController
{
    private const INDEX_ROUTES = ['roles' => 'mautic_role_index', 'users' => 'mautic_user_index'];

    /**
     * Core runs Mautic.<name>OnLoad for the page's mauticContent. "user" would run userOnLoad, which throws on a page without the UI settings
     * buttons and leaves the loading bar running. A name with no hook runs nothing.
     */
    private const CONTENT = 'roleUserClone';

    /**
     * The package, in the shared modal, of ?ids=[1,2] (one row, or the rows checked in the list: what core's bulk actions send), or of
     * every item when there is no ids. A GET that changes nothing: the key is made here and kept nowhere.
     */
    public function exportAction(string $kind, EntityManagerInterface $em, RoleUserExporter $exporter): Response
    {
        if (!$this->allowed($kind)) {
            return $this->accessDenied();
        }

        $chosen = $this->chosen($kind, $em, $this->requestedIds());
        if ([] === $chosen) {
            return $this->modalView('export', ['error' => $this->translator->trans('mautic.patches.role_user_clone.error.nothing_chosen')]);
        }

        $export = 'roles' === $kind ? $exporter->exportRoles($chosen) : $exporter->exportUsers($chosen);

        return $this->modalView('export', ['package' => $export->getPackage(), 'key' => $export->getKey(), 'count' => count($chosen), 'kind' => $kind]);
    }

    public function importFormAction(string $kind, FormFactoryInterface $formFactory): Response
    {
        if (!$this->allowed($kind)) {
            return $this->accessDenied();
        }

        return $this->importFormView($kind, $this->createImportForm($kind, $formFactory), []);
    }

    public function importSaveAction(string $kind, FormFactoryInterface $formFactory, RoleUserImporter $importer, RoleUserCloneAlerts $alerts): Response
    {
        if (!$this->allowed($kind)) {
            return $this->accessDenied();
        }

        $form = $this->createImportForm($kind, $formFactory);
        $form->handleRequest($this->getCurrentRequest());

        $package   = (string) ($form->get('package')->getData() ?? '');
        $key       = (string) ($form->get('key')->getData() ?? '');
        $confirmed = '1' === (string) $form->get('confirmed')->getData();

        // the form's token: without it a page of another site could post an import for a logged-in admin
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->importFormView($kind, $this->createImportForm($kind, $formFactory, false, $package, $key), [$this->translator->trans('mautic.patches.role_user_clone.error.token')]);
        }

        // the first submit only checks (nothing saved); the person confirms on the next one
        try {
            $result = $importer->import($package, $key, $confirmed);
        } catch (ClonePackageException $e) {
            return $this->importFormView($kind, $this->createImportForm($kind, $formFactory, false, $package, $key), [$this->translator->trans($e->getMessage())]);
        }

        $failuresJson = $result->hasFailures() ? $result->failuresJson() : '';
        if ($confirmed) {
            return $this->view($kind, 'import', ['done' => true, 'doneAlerts' => $alerts->fromResult($result), 'failuresJson' => $failuresJson]);
        }

        return $this->importFormView(
            $kind,
            $this->createImportForm($kind, $formFactory, true, $package, $key, $result->hasChanges()),
            [],
            $alerts->fromResult($result, true),
            $result->hasChanges(),
            $failuresJson
        );
    }

    private function allowed(string $kind): bool
    {
        return isset(self::INDEX_ROUTES[$kind]) && $this->security->isAdmin();
    }

    /**
     * @return int[]|null null when the request has no ids (export everything)
     */
    private function requestedIds(): ?array
    {
        $raw = $this->getCurrentRequest()->query->get('ids');
        if (null === $raw) {
            return null;
        }

        $ids = json_decode((string) $raw, true);

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)) : [];
    }

    /**
     * @param int[]|null $ids
     *
     * @return Role[]|User[]
     */
    private function chosen(string $kind, EntityManagerInterface $em, ?array $ids): array
    {
        $repository = $em->getRepository('roles' === $kind ? Role::class : User::class);
        if (null === $ids) {
            return $repository->findAll();
        }

        return [] === $ids ? [] : $repository->findBy(['id' => $ids]);
    }

    private function createImportForm(string $kind, FormFactoryInterface $formFactory, bool $confirming = false, string $package = '', string $key = '', bool $canConfirm = true): FormInterface
    {
        // while confirming, Cancel returns to the empty import page: a new check needs a new paste
        $cancel = $confirming ? $this->generateUrl('mautic_patches_role_user_clone_import', ['kind' => $kind]) : $this->generateUrl(self::INDEX_ROUTES[$kind]);

        return $formFactory->create(RoleUserCloneImportType::class, ['package' => $package, 'key' => $key], [
            'action'         => $this->generateUrl('mautic_patches_role_user_clone_import_save', ['kind' => $kind]),
            'cancel_onclick' => "mQuery('form[name=role_user_clone_import]').attr('action', '".$cancel."');",
            'confirming'     => $confirming,
            'can_confirm'    => $canConfirm,
        ]);
    }

    /**
     * @param string[]                                                          $errors
     * @param array<int, array{type: string, message: string, items: string[]}> $checkAlerts
     */
    private function importFormView(string $kind, FormInterface $form, array $errors, array $checkAlerts = [], bool $canConfirm = true, string $failuresJson = ''): Response
    {
        return $this->view($kind, 'import', [
            'done'         => false,
            'form'         => $form->createView(),
            'errors'       => $errors,
            'checkAlerts'  => $checkAlerts,
            'canConfirm'   => $canConfirm,
            'failuresJson' => $failuresJson,
        ]);
    }

    /**
     * The body of the shared modal (#MauticSharedModal), no page around it.
     *
     * @param array<string, mixed> $parameters
     */
    private function modalView(string $template, array $parameters): Response
    {
        return $this->delegateView([
            'viewParameters'  => $parameters,
            'contentTemplate' => '@MauticPatches/RoleUserClone/'.$template.'.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function view(string $kind, string $template, array $parameters): Response
    {
        $url = $this->generateUrl(self::INDEX_ROUTES[$kind]);

        return $this->delegateView([
            'returnUrl'       => $url,
            'viewParameters'  => $parameters + ['kind' => $kind, 'indexRoute' => self::INDEX_ROUTES[$kind]],
            'contentTemplate' => '@MauticPatches/RoleUserClone/'.$template.'.html.twig',
            'passthroughVars' => ['activeLink' => '#'.self::INDEX_ROUTES[$kind], 'mauticContent' => self::CONTENT, 'route' => $url],
        ]);
    }
}
