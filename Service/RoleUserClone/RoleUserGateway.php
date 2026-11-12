<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;

/**
 * What the import needs from this instance: look entities up, validate them and save them. A seam so that the import rules are
 * tested without a database.
 */
interface RoleUserGateway
{
    public function findRoleByName(string $name): ?Role;

    public function findUserByEmail(string $email): ?User;

    public function findUserByUsername(string $username): ?User;

    /**
     * @param array<string, string[]> $permissions "bundle:level" => names
     *
     * @return string[] the "bundle:level:name" lines that this instance does not know
     */
    public function unknownPermissions(array $permissions): array;

    /**
     * @return string[] the problems of the entity, empty when valid
     */
    public function validate(object $entity): array;

    /**
     * @param array<string, string[]> $permissions
     */
    public function saveRole(Role $role, array $permissions): void;

    public function saveUser(User $user): void;
}
