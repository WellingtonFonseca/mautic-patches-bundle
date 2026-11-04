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
    /** One rule for the pages and for the buttons that lead to them (FieldDefinitionButtonSubscriber). */
    public const PERMISSION = 'lead:fields:full';

    private const INDEX_ROUTE = 'mautic_contactfield_index';

    public function exportAction(FieldDefinitionExporter $exporter): Response
    {
        if (!$this->security->isGranted(self::PERMISSION)) {
            return $this->accessDenied();
        }

        return $this->view('export_definition', 'mautic_patches_field_definition_export', ['json' => $exporter->toJson()]);
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

        $form = $this->createImportForm($formFactory);
        $form->handleRequest($this->getCurrentRequest());

        $json      = (string) ($form->get('definition')->getData() ?? '');
        $confirmed = '1' === (string) $form->get('confirmed')->getData();

        // the form's token: without it a page of another site could post an import for a logged-in admin
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderErrors($formFactory, $json, [$this->translator->trans('mautic.patches.field_definition.error.token')]);
        }

        // the first submit only checks (nothing saved); the person confirms on the next one
        try {
            $result = $importer->import($json, $confirmed);
        } catch (FieldDefinitionImportException $e) {
            return $this->renderErrors($formFactory, $json, $e->getErrors());
        }

        if ($confirmed) {
            return $this->view('import_definition', 'mautic_patches_field_definition_import', ['done' => true, 'doneAlerts' => $alerts->fromResult($result)]);
        }

        return $this->renderImportForm(
            $this->createImportForm($formFactory, true, $json, $result->hasChanges()),
            [],
            $alerts->fromResult($result, true),
            $result->hasChanges()
        );
    }

    /**
     * @param string[] $errors
     */
    private function renderErrors(FormFactoryInterface $formFactory, string $json, array $errors): Response
    {
        return $this->renderImportForm($this->createImportForm($formFactory, false, $json), $errors);
    }

    private function createImportForm(FormFactoryInterface $formFactory, bool $confirming = false, string $json = '', bool $canConfirm = true): FormInterface
    {
        $cancel = $this->generateUrl(self::INDEX_ROUTE);

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
        return $this->view('import_definition', 'mautic_patches_field_definition_import', [
            'done'        => false,
            'form'        => $form->createView(),
            'errors'      => $errors,
            'checkAlerts' => $checkAlerts,
            'canConfirm'  => $canConfirm,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function view(string $template, string $route, array $parameters): Response
    {
        $url = $this->generateUrl($route);

        return $this->delegateView([
            'returnUrl'       => $url,
            'viewParameters'  => $parameters,
            'contentTemplate' => '@MauticPatches/Field/'.$template.'.html.twig',
            'passthroughVars' => ['activeLink' => '#'.self::INDEX_ROUTE, 'mauticContent' => 'leadfield', 'route' => $url],
        ]);
    }
}
