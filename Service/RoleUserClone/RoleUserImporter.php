<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\DTO\CloneImportResult;
use MauticPlugin\MauticPatchesBundle\Exception\ClonePackageException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Opens a roles / users package and applies it, entry by entry. An entry that cannot be imported is SKIPPED and listed with its
 * reason and its id on the source instance; the rest goes on. With apply=false nothing is saved (the check step).
 * Roles go first: a user needs its role, so a user of a skipped role is skipped too.
 */
class RoleUserImporter
{
    private const HASH_PATTERN = '/^\$2[abxy]\$\d{2}\$[.\/A-Za-z0-9]{53}$/';

    /** @var array<string, string[]> */
    private array $lists;

    /** @var array<int, array{kind: string, id: int, name: string, email: string, motivo: string}> */
    private array $failures;

    /** @var array<string, Role|null> role name => the role to use, null when the role was skipped */
    private array $roleByName;

    /** @var array<string, true> */
    private array $seen;

    public function __construct(
        private ClonePackage $package,
        private RoleUserGateway $gateway,
        private RoleSerializer $roles,
        private UserSerializer $users,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @throws ClonePackageException wrong key or a package of another shape: nothing is imported
     */
    public function import(string $text, string $key, bool $apply): CloneImportResult
    {
        $data = $this->package->decrypt($text, $key);
        $this->assertShape($data);

        $this->lists      = array_fill_keys(['rolesCreated', 'rolesUpdated', 'rolesUnchanged', 'usersCreated', 'usersUpdated', 'usersUnchanged'], []);
        $this->failures   = [];
        $this->roleByName = [];
        $this->seen       = [];

        foreach ($data['roles'] as $entry) {
            $this->importRole($entry, $apply);
        }
        $this->seen = [];
        foreach ($data['users'] ?? [] as $entry) {
            $this->importUser($entry, $apply);
        }

        return new CloneImportResult($this->lists, $this->failures);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertShape(array $data): void
    {
        $kind = $data['kind'] ?? null;
        $ok   = in_array($kind, [RoleUserExporter::KIND_ROLES, RoleUserExporter::KIND_USERS], true)
            && is_array($data['roles'] ?? null)
            && (RoleUserExporter::KIND_ROLES === $kind || is_array($data['users'] ?? null));
        if (!$ok) {
            throw new ClonePackageException('mautic.patches.role_user_clone.error.format');
        }
    }

    private function importRole(mixed $entry, bool $apply): void
    {
        $id   = is_array($entry) && is_int($entry['id'] ?? null) ? $entry['id'] : 0;
        $name = is_array($entry) && is_string($entry['name'] ?? null) ? $entry['name'] : '';

        if (!$this->validRoleEntry($entry)) {
            $this->failRole($id, $name, 'invalid_entry');

            return;
        }
        if (isset($this->seen[$name])) {
            $this->failRole($id, $name, 'duplicate_in_package');

            return;
        }
        $this->seen[$name] = true;

        $unknown = $this->gateway->unknownPermissions($entry['permissions']);
        if ([] !== $unknown) {
            $this->failRole($id, $name, 'unknown_permission', ['%items%' => implode(', ', $unknown)]);

            return;
        }

        $existing = $this->gateway->findRoleByName($name);
        $role     = $existing ?? new Role();
        if (null !== $existing && $this->sameRole($existing, $entry)) {
            $this->lists['rolesUnchanged'][] = $name;
            $this->roleByName[$name]         = $existing;

            return;
        }

        // a stored entity is changed only when applying: a check must leave it as it is, because a later flush would save it
        if (null === $existing || $apply) {
            $this->fillRole($role, $entry);
        }
        $problems = null === $existing ? $this->gateway->validate($role) : [];
        if ([] !== $problems) {
            $this->failRole($id, $name, 'invalid', ['%items%' => implode(', ', $problems)]);

            return;
        }

        $this->lists[null === $existing ? 'rolesCreated' : 'rolesUpdated'][] = $name;
        $this->roleByName[$name]                                             = $role;
        if ($apply) {
            $this->gateway->saveRole($role, $this->permissionsOf($entry));
        }
    }

    private function importUser(mixed $entry, bool $apply): void
    {
        $id       = is_array($entry) && is_int($entry['id'] ?? null) ? $entry['id'] : 0;
        $username = is_array($entry) && is_string($entry['username'] ?? null) ? $entry['username'] : '';
        $email    = is_array($entry) && is_string($entry['email'] ?? null) ? $entry['email'] : '';

        if (!$this->validUserEntry($entry)) {
            $this->failUser($id, $username, $email, 'invalid_entry');

            return;
        }
        if (isset($this->seen[$email])) {
            $this->failUser($id, $username, $email, 'duplicate_in_package');

            return;
        }
        $this->seen[$email] = true;

        $role = $this->roleFor($entry['role']);
        if (null === $role) {
            $reason = array_key_exists($entry['role'], $this->roleByName) ? 'role_failed' : 'role_missing';
            $this->failUser($id, $username, $email, $reason, ['%role%' => $entry['role']]);

            return;
        }

        $existing = $this->gateway->findUserByEmail($email);
        $owner    = $this->gateway->findUserByUsername($username);
        if (null !== $owner && $owner !== $existing) {
            $this->failUser($id, $username, $email, 'username_taken');

            return;
        }

        if (null !== $existing && $this->sameUser($existing, $entry)) {
            $this->lists['usersUnchanged'][] = $email;

            return;
        }

        $user = $existing ?? new User();
        if (null === $existing || $apply) {
            $this->fillUser($user, $entry, $role);
        }
        $problems = null === $existing ? $this->gateway->validate($user) : [];
        if ([] !== $problems) {
            $this->failUser($id, $username, $email, 'invalid', ['%items%' => implode(', ', $problems)]);

            return;
        }

        $this->lists[null === $existing ? 'usersCreated' : 'usersUpdated'][] = $email;
        if ($apply) {
            $this->gateway->saveUser($user);
        }
    }

    private function roleFor(string $name): ?Role
    {
        if (array_key_exists($name, $this->roleByName)) {
            return $this->roleByName[$name];
        }

        return $this->gateway->findRoleByName($name);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function sameRole(Role $existing, array $entry): bool
    {
        $candidate = new Role();
        $this->fillRole($candidate, $entry);

        return $this->withoutId($this->roles->toArray($existing)) === $this->withoutId($this->roles->toArray($candidate));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function sameUser(User $existing, array $entry): bool
    {
        $candidate = new User();
        $role      = new Role();
        $role->setName($entry['role']);
        $this->fillUser($candidate, $entry, $role);

        return $this->withoutId($this->users->toArray($existing)) === $this->withoutId($this->users->toArray($candidate));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withoutId(array $data): array
    {
        unset($data['id']);

        return $data;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function fillRole(Role $role, array $entry): void
    {
        $role->setName($entry['name']);
        $role->setDescription($entry['description'] ?? '');
        $role->setIsAdmin($entry['isAdmin']);
        // a stored role keeps its permission entities until saveRole replaces them; raw permissions are what the comparison reads
        $role->setRawPermissions($this->permissionsOf($entry));
    }

    /**
     * An admin role has no permissions: core ignores them for it, so none are saved.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, string[]>
     */
    private function permissionsOf(array $entry): array
    {
        return $entry['isAdmin'] ? [] : $entry['permissions'];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function fillUser(User $user, array $entry, Role $role): void
    {
        $user->setUsername($entry['username']);
        $user->setEmail($entry['email']);
        $user->setFirstName($entry['firstName'] ?? '');
        $user->setLastName($entry['lastName'] ?? '');
        $user->setPosition($entry['position'] ?? '');
        $user->setLocale($entry['locale'] ?? '');
        $user->setTimezone($entry['timezone'] ?? '');
        $user->setIsPublished($entry['isPublished']);
        $user->setPassword($entry['passwordHash']);
        $user->setRole($role);
    }

    private function validRoleEntry(mixed $entry): bool
    {
        if (!is_array($entry) || !is_string($entry['name'] ?? null) || '' === trim($entry['name'])) {
            return false;
        }
        if (!is_bool($entry['isAdmin'] ?? null) || !is_array($entry['permissions'] ?? null) || !$this->optionalText($entry, ['description'])) {
            return false;
        }
        foreach ($entry['permissions'] as $key => $names) {
            if (!is_string($key) || !is_array($names)) {
                return false;
            }
        }

        return true;
    }

    private function validUserEntry(mixed $entry): bool
    {
        if (!is_array($entry)) {
            return false;
        }
        foreach (['username', 'email', 'passwordHash', 'role'] as $required) {
            if (!is_string($entry[$required] ?? null) || '' === trim($entry[$required])) {
                return false;
            }
        }

        return false !== filter_var($entry['email'], FILTER_VALIDATE_EMAIL)
            && 1 === preg_match(self::HASH_PATTERN, $entry['passwordHash'])
            && is_bool($entry['isPublished'] ?? null)
            && $this->optionalText($entry, ['firstName', 'lastName', 'position', 'locale', 'timezone']);
    }

    /**
     * @param array<string, mixed> $entry
     * @param string[]             $keys
     */
    private function optionalText(array $entry, array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($entry[$key]) && !is_string($entry[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $params
     */
    private function failRole(int $id, string $name, string $reason, array $params = []): void
    {
        $this->roleByName[$name] = $this->roleByName[$name] ?? null;
        $this->failures[]        = ['kind' => 'role', 'id' => $id, 'name' => $name, 'email' => '', 'motivo' => $this->reason($reason, $params)];
    }

    /**
     * @param array<string, string> $params
     */
    private function failUser(int $id, string $username, string $email, string $reason, array $params = []): void
    {
        $this->failures[] = ['kind' => 'user', 'id' => $id, 'name' => $username, 'email' => $email, 'motivo' => $this->reason($reason, $params)];
    }

    /**
     * @param array<string, string> $params
     */
    private function reason(string $code, array $params): string
    {
        return $this->translator->trans('mautic.patches.role_user_clone.reason.'.$code, $params);
    }
}
