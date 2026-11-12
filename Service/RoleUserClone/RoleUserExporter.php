<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\DTO\CloneExport;

/**
 * Builds the encrypted package of the chosen roles, or of the chosen users. A users package ALWAYS carries the roles the users
 * have (each once), so the import never meets a user whose role is missing.
 */
class RoleUserExporter
{
    public const KIND_ROLES = 'roles';
    public const KIND_USERS = 'users';

    public function __construct(
        private ClonePackage $package,
        private RoleSerializer $roles,
        private UserSerializer $users
    ) {
    }

    /**
     * @param Role[] $roles
     */
    public function exportRoles(array $roles): CloneExport
    {
        $this->assertChosen($roles);

        return $this->seal(['kind' => self::KIND_ROLES, 'roles' => $this->serializeRoles($roles)]);
    }

    /**
     * @param User[] $users
     */
    public function exportUsers(array $users): CloneExport
    {
        $this->assertChosen($users);

        $roles = [];
        foreach ($users as $user) {
            $role = $user->getRole();
            if (null !== $role) {
                $roles[$role->getName()] = $role;
            }
        }

        $serialized = array_map(fn (User $user): array => $this->users->toArray($user), array_values($users));
        usort($serialized, static fn (array $a, array $b): int => strcmp($a['username'], $b['username']));

        return $this->seal(['kind' => self::KIND_USERS, 'roles' => $this->serializeRoles(array_values($roles)), 'users' => $serialized]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function seal(array $data): CloneExport
    {
        $key = $this->package->generateKey();

        return new CloneExport($this->package->encrypt($data, $key), $key);
    }

    /**
     * @param Role[] $roles
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeRoles(array $roles): array
    {
        $serialized = array_map(fn (Role $role): array => $this->roles->toArray($role), $roles);
        usort($serialized, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $serialized;
    }

    /**
     * @param object[] $chosen
     */
    private function assertChosen(array $chosen): void
    {
        if ([] === $chosen) {
            throw new \InvalidArgumentException('Nothing chosen to export.');
        }
    }
}
