<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserGateway;

/**
 * A gateway kept in memory: what exists "on this instance", what was saved and which names are invalid.
 */
class FakeRoleUserGateway implements RoleUserGateway
{
    /** @var array<string, Role> */
    public array $roles = [];

    /** @var User[] */
    public array $users = [];

    /** @var string[] */
    public array $knownPermissionKeys = ['lead:leads', 'user:users'];

    /** @var string[] */
    public array $invalidNames = [];

    /** @var string[] */
    public array $savedRoles = [];

    /** @var string[] */
    public array $savedUsers = [];

    public function findRoleByName(string $name): ?Role
    {
        return $this->roles[$name] ?? null;
    }

    public function findUserByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findUserByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getUsername() === $username) {
                return $user;
            }
        }

        return null;
    }

    public function unknownPermissions(array $permissions): array
    {
        $unknown = [];
        foreach ($permissions as $key => $names) {
            if (!in_array($key, $this->knownPermissionKeys, true)) {
                $unknown[] = $key;
            }
        }

        return $unknown;
    }

    public function validate(object $entity): array
    {
        $name = $entity instanceof Role ? $entity->getName() : $entity->getUsername();

        return in_array($name, $this->invalidNames, true) ? ['invalid '.$name] : [];
    }

    public function saveRole(Role $role, array $permissions): void
    {
        $role->setRawPermissions($permissions);
        $this->roles[$role->getName()] = $role;
        $this->savedRoles[]            = $role->getName();
    }

    public function saveUser(User $user): void
    {
        if (!in_array($user, $this->users, true)) {
            $this->users[] = $user;
        }
        $this->savedUsers[] = $user->getUsername();
    }
}
