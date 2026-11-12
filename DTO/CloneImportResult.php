<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

/**
 * What a roles / users import does (or would do). The lists hold role names and user e-mails. A failure is one entry that was
 * skipped: the import goes on with the rest. The failure list is a JSON to take to the instance the package came from.
 */
class CloneImportResult
{
    public const FAILURES_FORMAT = 'roles-users-clone-failures';

    /**
     * @param array<string, string[]>                                                          $lists    keys: rolesCreated, rolesUpdated, rolesUnchanged, usersCreated, usersUpdated, usersUnchanged
     * @param array<int, array{kind: string, id: int, name: string, email: string, motivo: string}> $failures
     */
    public function __construct(private array $lists, private array $failures)
    {
    }

    /**
     * @return string[]
     */
    public function get(string $list): array
    {
        return $this->lists[$list] ?? [];
    }

    /**
     * @return array<int, array{kind: string, id: int, name: string, email: string, motivo: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    public function hasChanges(): bool
    {
        foreach (['rolesCreated', 'rolesUpdated', 'usersCreated', 'usersUpdated'] as $list) {
            if ([] !== $this->get($list)) {
                return true;
            }
        }

        return false;
    }

    public function hasFailures(): bool
    {
        return [] !== $this->failures;
    }

    public function failuresJson(): string
    {
        return (string) json_encode(
            ['format' => self::FAILURES_FORMAT, 'version' => 1, 'failures' => $this->failures],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
