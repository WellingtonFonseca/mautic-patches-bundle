<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\FieldDefinition;

use MauticPlugin\MauticPatchesBundle\DTO\FieldDefinitionImportResult;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionImportAlerts;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldDefinitionImportAlertsTest extends TestCase
{
    private function alerts(): FieldDefinitionImportAlerts
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id, array $p = []): string => $id.($p ? ' '.implode(',', $p) : ''));

        return new FieldDefinitionImportAlerts($translator);
    }

    public function testTheOrderTheTypesAndTheItemsOfEveryAlert(): void
    {
        $result = (new FieldDefinitionImportResult(['lead: a'], ['lead: b', 'lead: c'], ['lead: d'], ['lead: d' => ['S1', 'S2'], 'lead: g' => ['S3']], ['lead: e'], ['lead: f'], 3))
            ->withColumnsInBackground(['lead: a']);

        $this->assertSame(
            [
                ['type' => 'success', 'message' => 'mautic.patches.field_definition.alert.created.preview 1', 'items' => ['lead: a']],
                ['type' => 'success', 'message' => 'mautic.patches.field_definition.alert.activated.preview 2', 'items' => ['lead: b', 'lead: c']],
                ['type' => 'success', 'message' => 'mautic.patches.field_definition.alert.deactivated.preview 1', 'items' => ['lead: d']],
                ['type' => 'warning', 'message' => 'mautic.patches.field_definition.alert.background.preview 1', 'items' => ['lead: a']],
                ['type' => 'warning', 'message' => 'mautic.patches.field_definition.alert.in_use 2', 'items' => ['lead: d — S1, S2', 'lead: g — S3']],
                ['type' => 'warning', 'message' => 'mautic.patches.field_definition.alert.kept_identifier 1', 'items' => ['lead: f']],
                ['type' => 'warning', 'message' => 'mautic.patches.field_definition.alert.kept_type 1', 'items' => ['lead: e']],
                ['type' => 'info', 'message' => 'mautic.patches.field_definition.alert.unchanged 3', 'items' => []],
            ],
            $this->alerts()->fromResult($result, true)
        );
    }

    public function testTheResultAfterTheApplyUsesThePastTexts(): void
    {
        $result = new FieldDefinitionImportResult(['lead: a'], [], [], [], [], [], 0);

        $this->assertSame(
            [['type' => 'success', 'message' => 'mautic.patches.field_definition.alert.created 1', 'items' => ['lead: a']]],
            $this->alerts()->fromResult($result)
        );
    }

    public function testAFileThatChangesNothingSaysSoAndCountsTheFieldsThatMatch(): void
    {
        $result = new FieldDefinitionImportResult([], [], [], [], [], [], 48);

        $this->assertSame(
            [['type' => 'info', 'message' => 'mautic.patches.field_definition.alert.nothing', 'items' => []], ['type' => 'info', 'message' => 'mautic.patches.field_definition.alert.unchanged 48', 'items' => []]],
            $this->alerts()->fromResult($result, true)
        );
    }
}
