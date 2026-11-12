<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use MauticPlugin\MauticPatchesBundle\DTO\CloneImportResult;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserCloneAlerts;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleUserCloneAlertsTest extends TestCase
{
    private function alerts(): RoleUserCloneAlerts
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id, array $p = []): string => str_replace('mautic.patches.role_user_clone.', '', $id).($p ? ' '.implode(',', $p) : ''));

        return new RoleUserCloneAlerts($translator);
    }

    /**
     * @param array<string, string[]> $lists
     */
    private function importResult(array $lists, array $failures = []): CloneImportResult
    {
        return new CloneImportResult($lists, $failures);
    }

    public function testTheOrderTheTypesAndTheItemsOfEveryAlert(): void
    {
        $failure = ['kind' => 'user', 'id' => 4, 'name' => 'ana', 'email' => 'a@x.com', 'motivo' => 'por quê'];
        $result  = $this->importResult(
            ['rolesCreated' => ['R1'], 'rolesUpdated' => ['R2', 'R3'], 'usersCreated' => ['u1'], 'usersUpdated' => ['u2'], 'rolesUnchanged' => ['R4'], 'usersUnchanged' => ['u3', 'u4']],
            [$failure]
        );

        $this->assertSame(
            [
                ['type' => 'success', 'message' => 'alert.roles_created 1', 'items' => ['R1']],
                ['type' => 'success', 'message' => 'alert.roles_updated 2', 'items' => ['R2', 'R3']],
                ['type' => 'success', 'message' => 'alert.users_created 1', 'items' => ['u1']],
                ['type' => 'success', 'message' => 'alert.users_updated 1', 'items' => ['u2']],
                ['type' => 'danger', 'message' => 'alert.failures 1', 'items' => ['alert.failure_line kind.user,ana,4,por quê']],
                ['type' => 'info', 'message' => 'alert.unchanged 3', 'items' => []],
            ],
            $this->alerts()->fromResult($result)
        );
    }

    public function testThePreviewUsesThePreviewTexts(): void
    {
        $failure = ['kind' => 'role', 'id' => 1, 'name' => 'R', 'email' => '', 'motivo' => 'm'];
        $result  = $this->importResult(['rolesCreated' => ['R1'], 'usersUpdated' => ['u']], [$failure]);

        $messages = array_column($this->alerts()->fromResult($result, true), 'message');

        $this->assertSame(['alert.roles_created.preview 1', 'alert.users_updated.preview 1', 'alert.failures.preview 1'], $messages);
    }

    public function testNothingToDoSaysSo(): void
    {
        $this->assertSame(
            [['type' => 'info', 'message' => 'alert.nothing', 'items' => []]],
            $this->alerts()->fromResult($this->importResult([]))
        );
    }

    public function testOnlyFailuresIsNotNothingToDo(): void
    {
        $failure = ['kind' => 'role', 'id' => 1, 'name' => 'R', 'email' => '', 'motivo' => 'm'];

        $this->assertSame(['danger'], array_column($this->alerts()->fromResult($this->importResult([], [$failure])), 'type'));
    }
}
