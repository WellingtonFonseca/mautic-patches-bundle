<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\DTO\CloneImportResult;
use MauticPlugin\MauticPatchesBundle\Exception\ClonePackageException;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\ClonePackage;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleSerializer;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserImporter;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\UserSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleUserImporterTest extends TestCase
{
    private const HASH = '$2y$13$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private FakeRoleUserGateway $gateway;
    private ClonePackage $package;

    protected function setUp(): void
    {
        $this->gateway = new FakeRoleUserGateway();
        $this->package = new ClonePackage();
    }

    private function importer(): RoleUserImporter
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id, array $p = []): string => $id.($p ? ' '.implode(',', $p) : ''));

        return new RoleUserImporter($this->package, $this->gateway, new RoleSerializer(), new UserSerializer(), $translator);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: string, 1: string} the package and its key
     */
    private function sealed(array $data): array
    {
        $key = $this->package->generateKey();

        return [$this->package->encrypt($data, $key), $key];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function roleEntry(string $name, array $override = []): array
    {
        return array_merge(['id' => 5, 'name' => $name, 'description' => 'd', 'isAdmin' => false, 'permissions' => ['lead:leads' => ['view']]], $override);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function userEntry(string $username, string $role = 'Gestão', array $override = []): array
    {
        return array_merge([
            'id' => 9, 'username' => $username, 'email' => $username.'@example.com', 'firstName' => 'A', 'lastName' => 'B', 'position' => 'P',
            'locale' => 'pt_BR', 'timezone' => 'America/Sao_Paulo', 'isPublished' => true, 'passwordHash' => self::HASH, 'role' => $role,
        ], $override);
    }

    /**
     * @param array<int, array<string, mixed>> $roles
     * @param array<int, array<string, mixed>> $users
     */
    private function importPackage(array $roles, array $users = [], bool $apply = true): CloneImportResult
    {
        [$text, $key] = $this->sealed(['kind' => [] === $users ? 'roles' : 'users', 'roles' => $roles, 'users' => $users]);

        return $this->importer()->import($text, $key, $apply);
    }

    private function existingRole(string $name, string $description = 'd', array $permissions = ['lead:leads' => ['view']]): Role
    {
        $role = new Role();
        $role->setName($name);
        $role->setDescription($description);
        $role->setIsAdmin(false);
        $role->setRawPermissions($permissions);
        $this->gateway->roles[$name] = $role;

        return $role;
    }

    private function existingUser(string $username, Role $role, array $override = []): User
    {
        $entry = array_merge($this->userEntry($username, $role->getName()), $override);
        $user  = new User();
        $user->setUsername($entry['username']);
        $user->setEmail($entry['email']);
        $user->setFirstName($entry['firstName']);
        $user->setLastName($entry['lastName']);
        $user->setPosition($entry['position']);
        $user->setLocale($entry['locale']);
        $user->setTimezone($entry['timezone']);
        $user->setIsPublished($entry['isPublished']);
        $user->setPassword($entry['passwordHash']);
        $user->setRole($role);
        $this->gateway->users[] = $user;

        return $user;
    }

    public function testANewRoleIsCreatedWithItsData(): void
    {
        $result = $this->importPackage([$this->roleEntry('Gestão', ['isAdmin' => false, 'description' => 'Equipe'])]);

        $this->assertSame(['Gestão'], $result->get('rolesCreated'));
        $this->assertSame(['Gestão'], $this->gateway->savedRoles);
        $role = $this->gateway->roles['Gestão'];
        $this->assertSame('Equipe', $role->getDescription());
        $this->assertSame(['lead:leads' => ['view']], $role->getRawPermissions());
    }

    public function testACheckSavesNothing(): void
    {
        $role   = $this->existingRole('Velha', 'antiga');
        $result = $this->importPackage([$this->roleEntry('Nova'), $this->roleEntry('Velha', ['description' => 'nova'])], [], false);

        $this->assertSame(['Nova'], $result->get('rolesCreated'));
        $this->assertSame(['Velha'], $result->get('rolesUpdated'));
        $this->assertSame([], $this->gateway->savedRoles);
        $this->assertSame('antiga', $role->getDescription());
    }

    public function testARoleThatMatchesIsUnchangedAndNotSaved(): void
    {
        $this->existingRole('Gestão');
        $result = $this->importPackage([$this->roleEntry('Gestão')]);

        $this->assertSame(['Gestão'], $result->get('rolesUnchanged'));
        $this->assertSame([], $this->gateway->savedRoles);
        $this->assertFalse($result->hasChanges());
    }

    public function testPermissionOrderDoesNotMakeARoleChanged(): void
    {
        $this->existingRole('Gestão', 'd', ['lead:leads' => ['view', 'edit']]);
        $result = $this->importPackage([$this->roleEntry('Gestão', ['permissions' => ['lead:leads' => ['edit', 'view']]])]);

        $this->assertSame(['Gestão'], $result->get('rolesUnchanged'));
    }

    public function testADifferentPermissionUpdatesTheExistingRole(): void
    {
        $role   = $this->existingRole('Gestão');
        $result = $this->importPackage([$this->roleEntry('Gestão', ['permissions' => ['lead:leads' => ['full']]])]);

        $this->assertSame(['Gestão'], $result->get('rolesUpdated'));
        $this->assertSame(['lead:leads' => ['full']], $role->getRawPermissions());
    }

    public function testARoleWithAPermissionThisInstanceDoesNotKnowIsSkippedAndTheRestGoesOn(): void
    {
        $result = $this->importPackage([$this->roleEntry('Boa'), $this->roleEntry('Ruim', ['id' => 77, 'permissions' => ['plugin:x' => ['a']]])]);

        $this->assertSame(['Boa'], $result->get('rolesCreated'));
        $this->assertSame(['Boa'], $this->gateway->savedRoles);
        $failure = $result->getFailures()[0];
        $this->assertSame(['kind' => 'role', 'id' => 77, 'name' => 'Ruim', 'email' => ''], array_diff_key($failure, ['motivo' => 1]));
        $this->assertSame('mautic.patches.role_user_clone.reason.unknown_permission plugin:x', $failure['motivo']);
    }

    public function testAnInvalidRoleEntryIsSkippedWithTheReason(): void
    {
        $result = $this->importPackage([['id' => 3, 'name' => '', 'permissions' => []], $this->roleEntry('Boa')]);

        $this->assertSame(['Boa'], $result->get('rolesCreated'));
        $this->assertSame(3, $result->getFailures()[0]['id']);
        $this->assertSame('mautic.patches.role_user_clone.reason.invalid_entry', $result->getFailures()[0]['motivo']);
    }

    public function testARoleThatTheValidatorRefusesIsSkipped(): void
    {
        $this->gateway->invalidNames = ['Ruim'];
        $result                      = $this->importPackage([$this->roleEntry('Ruim')]);

        $this->assertSame([], $result->get('rolesCreated'));
        $this->assertSame('mautic.patches.role_user_clone.reason.invalid invalid Ruim', $result->getFailures()[0]['motivo']);
    }

    public function testTheSameRoleNameTwiceInOnePackageFailsTheSecond(): void
    {
        $result = $this->importPackage([$this->roleEntry('A'), $this->roleEntry('A', ['id' => 6])]);

        $this->assertSame(['A'], $result->get('rolesCreated'));
        $this->assertSame(6, $result->getFailures()[0]['id']);
        $this->assertSame('mautic.patches.role_user_clone.reason.duplicate_in_package', $result->getFailures()[0]['motivo']);
    }

    public function testANewUserIsCreatedWithTheHashAndTheRole(): void
    {
        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana')]);

        $this->assertSame(['ana@example.com'], $result->get('usersCreated'));
        $user = $this->gateway->users[0];
        $this->assertSame(self::HASH, $user->getPassword());
        $this->assertSame($this->gateway->roles['Gestão'], $user->getRole());
        $this->assertSame(['Gestão'], $this->gateway->savedRoles);
        $this->assertSame(['ana'], $this->gateway->savedUsers);
        $this->assertSame('pt_BR', $user->getLocale());
        $this->assertSame('America/Sao_Paulo', $user->getTimezone());
        $this->assertSame('P', $user->getPosition());
        $this->assertSame('A', $user->getFirstName());
        $this->assertSame('B', $user->getLastName());
        $this->assertTrue($user->isPublished());
    }

    public function testAUserFoundByEmailIsUpdatedAndAnEqualOneIsUnchanged(): void
    {
        $role = $this->existingRole('Gestão');
        $same = $this->existingUser('ana', $role);
        $diff = $this->existingUser('bia', $role, ['position' => 'antigo']);

        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana'), $this->userEntry('bia')]);

        $this->assertSame(['ana@example.com'], $result->get('usersUnchanged'));
        $this->assertSame(['bia@example.com'], $result->get('usersUpdated'));
        $this->assertSame('P', $diff->getPosition());
        $this->assertSame(['bia'], $this->gateway->savedUsers);
        $this->assertSame($role, $same->getRole());
    }

    public function testADifferentPasswordHashOrStatusUpdatesTheUser(): void
    {
        $role = $this->existingRole('Gestão');
        $this->existingUser('ana', $role, ['passwordHash' => '$2y$12$old', 'isPublished' => false]);

        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana')]);

        $this->assertSame(['ana@example.com'], $result->get('usersUpdated'));
        $this->assertSame(self::HASH, $this->gateway->users[0]->getPassword());
        $this->assertTrue($this->gateway->users[0]->isPublished());
    }

    public function testAUsernameOfAnotherEmailSkipsOnlyThatUserAndTheRestGoesOn(): void
    {
        $role = $this->existingRole('Gestão');
        $this->existingUser('ana', $role, ['email' => 'outra@example.com']);

        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana', 'Gestão', ['id' => 41]), $this->userEntry('bia')]);

        $this->assertSame(['bia@example.com'], $result->get('usersCreated'));
        $failure = $result->getFailures()[0];
        $this->assertSame('user', $failure['kind']);
        $this->assertSame(41, $failure['id']);
        $this->assertSame('ana', $failure['name']);
        $this->assertSame('ana@example.com', $failure['email']);
        $this->assertSame('mautic.patches.role_user_clone.reason.username_taken', $failure['motivo']);
    }

    public function testChangingTheUsernameOfAUserFoundByEmailIsAllowedWhenFree(): void
    {
        $role = $this->existingRole('Gestão');
        $this->existingUser('antigo', $role, ['email' => 'ana@example.com']);

        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana')]);

        $this->assertSame(['ana@example.com'], $result->get('usersUpdated'));
        $this->assertSame('ana', $this->gateway->users[0]->getUsername());
    }

    public function testAUserOfASkippedRoleIsSkipped(): void
    {
        $result = $this->importPackage([$this->roleEntry('Ruim', ['permissions' => ['plugin:x' => ['a']]])], [$this->userEntry('ana', 'Ruim', ['id' => 12])]);

        $this->assertSame([], $result->get('usersCreated'));
        $this->assertSame(['role', 'user'], array_column($result->getFailures(), 'kind'));
        $this->assertSame('mautic.patches.role_user_clone.reason.role_failed Ruim', $result->getFailures()[1]['motivo']);
        $this->assertSame(12, $result->getFailures()[1]['id']);
    }

    public function testARoleOnlyOnThisInstanceServesTheUser(): void
    {
        $role = $this->existingRole('Local');
        $result = $this->importPackage([], [$this->userEntry('ana', 'Local')]);

        $this->assertSame(['ana@example.com'], $result->get('usersCreated'));
        $this->assertSame($role, $this->gateway->users[0]->getRole());
    }

    public function testAUserWhoseRoleExistsNowhereIsSkipped(): void
    {
        $result = $this->importPackage([], [$this->userEntry('ana', 'Fantasma')]);

        $this->assertSame('mautic.patches.role_user_clone.reason.role_missing Fantasma', $result->getFailures()[0]['motivo']);
        $this->assertSame([], $this->gateway->savedUsers);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('badUsers')]
    public function testAnInvalidUserEntryIsSkipped(array $override): void
    {
        $this->existingRole('Gestão');
        $result = $this->importPackage([], [$this->userEntry('ana', 'Gestão', $override), $this->userEntry('bia')]);

        $this->assertSame(['bia@example.com'], $result->get('usersCreated'));
        $this->assertSame('mautic.patches.role_user_clone.reason.invalid_entry', $result->getFailures()[0]['motivo']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badUsers(): array
    {
        return [
            'bad email'      => [['email' => 'not-an-email']],
            'no username'    => [['username' => '']],
            'plain password' => [['passwordHash' => 'secret']],
            'no role'        => [['role' => '']],
            'published text' => [['isPublished' => 'yes']],
            'name not text'  => [['firstName' => 5]],
        ];
    }

    public function testTheSameEmailTwiceInOnePackageFailsTheSecond(): void
    {
        $this->existingRole('Gestão');
        $result = $this->importPackage([], [$this->userEntry('ana'), $this->userEntry('outro', 'Gestão', ['email' => 'ana@example.com', 'id' => 30])]);

        $this->assertSame(['ana@example.com'], $result->get('usersCreated'));
        $this->assertSame(30, $result->getFailures()[0]['id']);
        $this->assertSame('mautic.patches.role_user_clone.reason.duplicate_in_package', $result->getFailures()[0]['motivo']);
    }

    public function testAUserTheValidatorRefusesIsSkipped(): void
    {
        $this->existingRole('Gestão');
        $this->gateway->invalidNames = ['ana'];
        $result                      = $this->importPackage([], [$this->userEntry('ana')]);

        $this->assertSame([], $this->gateway->savedUsers);
        $this->assertSame('mautic.patches.role_user_clone.reason.invalid invalid ana', $result->getFailures()[0]['motivo']);
    }

    public function testACheckOfUsersSavesNothing(): void
    {
        $result = $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana')], false);

        $this->assertSame(['ana@example.com'], $result->get('usersCreated'));
        $this->assertSame([], $this->gateway->savedUsers);
        $this->assertSame([], $this->gateway->savedRoles);
    }

    public function testTheFailuresJsonCarriesTheReasonAndTheSourceId(): void
    {
        $result = $this->importPackage([$this->roleEntry('Ruim', ['id' => 77, 'permissions' => ['plugin:x' => ['a']]])]);
        $json   = json_decode($result->failuresJson(), true);

        $this->assertSame('roles-users-clone-failures', $json['format']);
        $this->assertSame(1, $json['version']);
        $this->assertSame(77, $json['failures'][0]['id']);
        $this->assertSame('mautic.patches.role_user_clone.reason.unknown_permission plugin:x', $json['failures'][0]['motivo']);
        $this->assertTrue($result->hasFailures());
    }

    public function testAWrongKeyImportsNothing(): void
    {
        [$text] = $this->sealed(['kind' => 'roles', 'roles' => [$this->roleEntry('A')]]);

        $this->expectException(ClonePackageException::class);
        try {
            $this->importer()->import($text, $this->package->generateKey(), true);
        } finally {
            $this->assertSame([], $this->gateway->savedRoles);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('badPackages')]
    public function testAPackageOfAnotherShapeIsRefused(array $data): void
    {
        [$text, $key] = $this->sealed($data);

        $this->expectException(ClonePackageException::class);
        $this->expectExceptionMessage('format');
        $this->importer()->import($text, $key, true);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badPackages(): array
    {
        return [
            'unknown kind'      => [['kind' => 'x', 'roles' => []]],
            'no roles list'     => [['kind' => 'roles']],
            'users without list' => [['kind' => 'users', 'roles' => []]],
        ];
    }

    public function testACheckLeavesAStoredUserAsItIs(): void
    {
        $role = $this->existingRole('Gestão');
        $user = $this->existingUser('ana', $role, ['position' => 'antigo']);

        $this->importPackage([$this->roleEntry('Gestão')], [$this->userEntry('ana')], false);

        $this->assertSame('antigo', $user->getPosition());
    }

    /**
     * @param mixed $entry
     */
    #[DataProvider('badRoleEntries')]
    public function testAnInvalidRoleEntryIsSkippedWhateverIsWrong(mixed $entry, int $id, string $name): void
    {
        $result = $this->importPackage([$entry, $this->roleEntry('Boa')]);

        $this->assertSame(['Boa'], $result->get('rolesCreated'));
        $this->assertSame(1, count($result->getFailures()));
        $this->assertSame($id, $result->getFailures()[0]['id']);
        $this->assertSame($name, $result->getFailures()[0]['name']);
        $this->assertSame('mautic.patches.role_user_clone.reason.invalid_entry', $result->getFailures()[0]['motivo']);
        $this->assertSame(['Boa'], $this->gateway->savedRoles);
    }

    /**
     * @return array<string, array{mixed, int, string}>
     */
    public static function badRoleEntries(): array
    {
        $ok = ['id' => 4, 'name' => 'X', 'description' => 'd', 'isAdmin' => false, 'permissions' => []];

        return [
            'not an object'       => ['text', 0, ''],
            'name not text'       => [['id' => 4, 'name' => 5] + $ok, 4, ''],
            'name blank'          => [['name' => '   '] + $ok, 4, '   '],
            'no admin flag'       => [array_diff_key($ok, ['isAdmin' => 1]), 4, 'X'],
            'admin flag as text'  => [['isAdmin' => 'yes'] + $ok, 4, 'X'],
            'permissions text'    => [['permissions' => 'all'] + $ok, 4, 'X'],
            'no permissions'      => [array_diff_key($ok, ['permissions' => 1]), 4, 'X'],
            'description number'  => [['description' => 5] + $ok, 4, 'X'],
            'permission int key'  => [['permissions' => [0 => ['view']]] + $ok, 4, 'X'],
            'permission not list' => [['permissions' => ['lead:leads' => 'view']] + $ok, 4, 'X'],
            'id as text'          => [['id' => '7', 'name' => ''] + $ok, 0, ''],
            'no id'               => [['name' => ''] + array_diff_key($ok, ['id' => 1]), 0, ''],
        ];
    }

    public function testAnAdminRoleKeepsTheFlagAndHasNoPermissions(): void
    {
        $this->importPackage([$this->roleEntry('Chefe', ['isAdmin' => true, 'permissions' => ['lead:leads' => ['view']]])]);

        $role = $this->gateway->roles['Chefe'];
        $this->assertTrue($role->isAdmin());
        $this->assertSame([], $role->getRawPermissions());
    }

    public function testADuplicateRoleIsNotImportedAgainNorCountedAsMatching(): void
    {
        $result = $this->importPackage([$this->roleEntry('A'), $this->roleEntry('A', ['id' => 6])]);

        $this->assertSame([], $result->get('rolesUnchanged'));
        $this->assertSame(['A'], $this->gateway->savedRoles);
        $this->assertCount(1, $result->getFailures());
    }

    public function testADuplicateUserEmailIsNotImportedAgainNorCountedAsMatching(): void
    {
        $this->existingRole('Gestão');
        $result = $this->importPackage([], [$this->userEntry('ana'), $this->userEntry('outro', 'Gestão', ['email' => 'ana@example.com'])]);

        $this->assertSame([], $result->get('usersUnchanged'));
        $this->assertSame(['ana'], $this->gateway->savedUsers);
        $this->assertCount(1, $result->getFailures());
    }

    public function testACheckStillValidatesANewRoleAndANewUser(): void
    {
        $this->gateway->invalidNames = ['Ruim', 'ana'];
        $this->existingRole('Gestão');

        $roles = $this->importPackage([$this->roleEntry('Ruim')], [], false);
        $users = $this->importPackage([], [$this->userEntry('ana')], false);

        $this->assertSame([], $roles->get('rolesCreated'));
        $this->assertCount(1, $roles->getFailures());
        $this->assertSame([], $users->get('usersCreated'));
        $this->assertCount(1, $users->getFailures());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('badUserParts')]
    public function testEveryRequiredTextOfAUserMustHaveContent(array $override): void
    {
        $this->existingRole('Gestão');
        $result = $this->importPackage([], [$this->userEntry('ana', 'Gestão', $override)]);

        $this->assertSame('mautic.patches.role_user_clone.reason.invalid_entry', $result->getFailures()[0]['motivo']);
        $this->assertSame([], $this->gateway->savedUsers);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badUserParts(): array
    {
        return [
            'username spaces' => [['username' => '   ']],
            'email spaces'    => [['email' => '  ']],
            'role spaces'     => [['role' => '  ']],
            'hash spaces'     => [['passwordHash' => ' ']],
            'locale number'   => [['locale' => 5]],
            'position number' => [['position' => 5]],
        ];
    }

    public function testAUserEntryThatIsNotAnObjectHasNoIdOrName(): void
    {
        [$text, $key] = $this->sealed(['kind' => 'users', 'roles' => [], 'users' => ['text', ['id' => '3', 'username' => 7, 'email' => 8]]]);
        $result       = $this->importer()->import($text, $key, true);

        $this->assertSame([['user', 0, '', ''], ['user', 0, '', '']], array_map(static fn (array $f): array => [$f['kind'], $f['id'], $f['name'], $f['email']], $result->getFailures()));
    }
}
