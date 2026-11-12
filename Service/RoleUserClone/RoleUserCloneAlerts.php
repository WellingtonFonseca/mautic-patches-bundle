<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use MauticPlugin\MauticPatchesBundle\DTO\CloneImportResult;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The alerts of the check and of the result of a roles / users import: what changes first, then the entries that were skipped,
 * then what already matched (only counted).
 */
class RoleUserCloneAlerts
{
    private const CHANGES = [
        'rolesCreated' => 'roles_created',
        'rolesUpdated' => 'roles_updated',
        'usersCreated' => 'users_created',
        'usersUpdated' => 'users_updated',
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @return array<int, array{type: string, message: string, items: string[]}>
     */
    public function fromResult(CloneImportResult $result, bool $preview = false): array
    {
        $suffix = $preview ? '.preview' : '';
        $alerts = [];

        foreach (self::CHANGES as $list => $key) {
            $this->add($alerts, 'success', $key.$suffix, $result->get($list));
        }
        $this->addFailures($alerts, $result, $preview);

        if (!$result->hasChanges() && !$result->hasFailures()) {
            $alerts[] = ['type' => 'info', 'message' => $this->translator->trans('mautic.patches.role_user_clone.alert.nothing'), 'items' => []];
        }
        $unchanged = count($result->get('rolesUnchanged')) + count($result->get('usersUnchanged'));
        if ($unchanged > 0) {
            $alerts[] = ['type' => 'info', 'message' => $this->translator->trans('mautic.patches.role_user_clone.alert.unchanged', ['%count%' => $unchanged]), 'items' => []];
        }

        return $alerts;
    }

    /**
     * @param array<int, array{type: string, message: string, items: string[]}> $alerts
     */
    private function addFailures(array &$alerts, CloneImportResult $result, bool $preview): void
    {
        $lines = [];
        foreach ($result->getFailures() as $failure) {
            $lines[] = $this->translator->trans('mautic.patches.role_user_clone.alert.failure_line', [
                '%kind%'   => $this->translator->trans('mautic.patches.role_user_clone.kind.'.$failure['kind']),
                '%name%'   => $failure['name'],
                '%id%'     => $failure['id'],
                '%motivo%' => $failure['motivo'],
            ]);
        }
        $this->add($alerts, 'danger', 'failures'.($preview ? '.preview' : ''), $lines);
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

        $alerts[] = ['type' => $type, 'message' => $this->translator->trans('mautic.patches.role_user_clone.alert.'.$key, ['%count%' => count($items)]), 'items' => $items];
    }
}
