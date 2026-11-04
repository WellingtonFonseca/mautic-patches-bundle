<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\FieldDefinition;

use MauticPlugin\MauticPatchesBundle\DTO\FieldDefinitionImportResult;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The alerts of the check and of the result of a field definition import, in the order of the importance for who reads: what
 * changes first, then what stays as it is. A field that already matches the file is only counted.
 */
class FieldDefinitionImportAlerts
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @return array<int, array{type: string, message: string, items: string[]}>
     */
    public function fromResult(FieldDefinitionImportResult $result, bool $preview = false): array
    {
        $suffix = $preview ? '.preview' : '';
        $alerts = [];

        $this->add($alerts, 'success', 'created'.$suffix, $result->getCreated());
        $this->add($alerts, 'success', 'activated'.$suffix, $result->getActivated());
        $this->add($alerts, 'success', 'deactivated'.$suffix, $result->getDeactivated());
        $this->add($alerts, 'warning', 'background'.$suffix, $result->getColumnsInBackground());
        $this->add($alerts, 'warning', 'in_use', $this->inUseLines($result));
        $this->add($alerts, 'warning', 'kept_identifier', $result->getKeptIdentifier());
        $this->add($alerts, 'warning', 'kept_type', $result->getKeptType());

        if (!$result->hasChanges()) {
            $alerts[] = ['type' => 'info', 'message' => $this->translator->trans('mautic.patches.field_definition.alert.nothing'), 'items' => []];
        }
        if ($result->getUnchanged() > 0) {
            $alerts[] = ['type' => 'info', 'message' => $this->translator->trans('mautic.patches.field_definition.alert.unchanged', ['%count%' => $result->getUnchanged()]), 'items' => []];
        }

        return $alerts;
    }

    /**
     * @return string[]
     */
    private function inUseLines(FieldDefinitionImportResult $result): array
    {
        $lines = [];
        foreach ($result->getDeactivatedInUse() as $field => $segments) {
            $lines[] = $field.' — '.implode(', ', $segments);
        }

        return $lines;
    }

    /**
     * @param array<int, array{type: string, message: string, items: string[]}> $alerts
     * @param string[]                                                          $items
     */
    private function add(array &$alerts, string $type, string $key, array $items): void
    {
        if ([] === $items) {
            return;
        }

        $alerts[] = ['type' => $type, 'message' => $this->translator->trans('mautic.patches.field_definition.alert.'.$key, ['%count%' => count($items)]), 'items' => $items];
    }
}
