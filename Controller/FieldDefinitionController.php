<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;
use MauticPlugin\MauticPatchesBundle\Form\Type\FieldDefinitionImportType;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionExporter;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionImportAlerts;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionImporter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clone of the contact and company fields to another instance: the page that shows the definition (Exportar) and the page that
 * reads it (Importar: a check first, then the confirmation). Only for who may manage fields.
 */
class FieldDefinitionController extends CommonController
{
    private const PERMISSION = 'lead:fields:full';

    public function exportAction(FieldDefinitionExporter $exporter): Response
    {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'returnUrl'       => $this->generateUrl('mautic_patches_field_definition_export'),
            'viewParameters'  => ['json' => $exporter->toJson()],
            'contentTemplate' => '@MauticPatches/Field/export_definition.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_contactfield_index',
                'mauticContent' => 'leadfield',
                'route'         => $this->generateUrl('mautic_patches_field_definition_export'),
            ],
        ]);
    }

    public function importFormAction(FormFactoryInterface $formFactory): Response
    {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return $this->accessDenied();
        }

        return $this->renderImportForm($this->createImportForm($formFactory), []);
    }

    public function importSaveAction(
        FormFactoryInterface $formFactory,
        FieldDefinitionImporter $importer,
        FieldDefinitionImportAlerts $alerts
    ): Response {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return $this->accessDenied();
        }

        $request = $this->getCurrentRequest();
        $form    = $this->createImportForm($formFactory);
        $form->handleRequest($request);

        $json      = (string) ($form->get('definition')->getData() ?? '');
        $confirmed = '1' === (string) $form->get('confirmed')->getData();

        // the form's token: without it a page of another site could post an import for a logged-in admin
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderImportForm($this->createImportForm($formFactory, false, $json), [$this->translator->trans('mautic.patches.field_definition.error.token')]);
        }

        // first the check: what the import WOULD do, nothing saved; the person confirms it on the next submit
        if (!$confirmed) {
            return $this->checkStep($formFactory, $importer, $alerts, $json);
        }

        try {
            $result = $importer->import($json);
        } catch (FieldDefinitionImportException $e) {
            return $this->renderImportForm($this->createImportForm($formFactory, false, $json), $e->getErrors());
        }

        return $this->delegateView([
            'returnUrl'       => $this->generateUrl('mautic_contactfield_index'),
            'viewParameters'  => ['done' => true, 'doneAlerts' => $alerts->fromResult($result)],
            'contentTemplate' => '@MauticPatches/Field/import_definition.html.twig',
            'passthroughVars' => ['activeLink' => '#mautic_contactfield_index', 'mauticContent' => 'leadfield', 'route' => $this->generateUrl('mautic_patches_field_definition_import')],
        ]);
    }

    private function checkStep(FormFactoryInterface $formFactory, FieldDefinitionImporter $importer, FieldDefinitionImportAlerts $alerts, string $json): Response
    {
        try {
            $check = $importer->import($json, false);
        } catch (FieldDefinitionImportException $e) {
            return $this->renderImportForm($this->createImportForm($formFactory, false, $json), $e->getErrors());
        }

        return $this->renderImportForm(
            $this->createImportForm($formFactory, true, $json, $check->hasChanges()),
            [],
            $alerts->fromResult($check, true),
            $check->hasChanges()
        );
    }

    private function createImportForm(FormFactoryInterface $formFactory, bool $confirming = false, string $json = '', bool $canConfirm = true): FormInterface
    {
        $cancel = $this->generateUrl('mautic_contactfield_index');

        return $formFactory->create(FieldDefinitionImportType::class, ['definition' => $json], [
            'action'         => $this->generateUrl('mautic_patches_field_definition_import_save'),
            'cancel_onclick' => "mQuery('form[name=field_definition_import]').attr('action', '".$cancel."');",
            'confirming'     => $confirming,
            'can_confirm'    => $canConfirm,
        ]);
    }

    /**
     * @param string[]                                                          $errors
     * @param array<int, array{type: string, message: string, items: string[]}> $checkAlerts what the import would do (the check step)
     */
    private function renderImportForm(FormInterface $form, array $errors, array $checkAlerts = [], bool $canConfirm = true): Response
    {
        $route = $this->generateUrl('mautic_patches_field_definition_import');

        return $this->delegateView([
            'returnUrl'       => $route,
            'viewParameters'  => ['done' => false, 'form' => $form->createView(), 'errors' => $errors, 'checkAlerts' => $checkAlerts, 'canConfirm' => $canConfirm],
            'contentTemplate' => '@MauticPatches/Field/import_definition.html.twig',
            'passthroughVars' => ['activeLink' => '#mautic_contactfield_index', 'mauticContent' => 'leadfield', 'route' => $route],
        ]);
    }
}
