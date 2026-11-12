<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;

/**
 * A role as the clone package holds it. The role is found by its name on the other instance; the id is only the id here, so the failure
 * list of an import points back to the role on this instance.
 */
class RoleSerializer
{
    /**
     * @return array{id: int, name: string, description: string, isAdmin: bool, permissions: array<string, string[]>}
     */
    public function toArray(Role $role): array
    {
        return [
            'id'          => (int) $role->getId(),
            'name'        => (string) $role->getName(),
            'description' => (string) $role->getDescription(),
            'isAdmin'     => $role->isAdmin(),
            'permissions' => $role->isAdmin() ? [] : $this->sortedPermissions($role),
        ];
    }

    /**
     * @return array<string, string[]>
     */
    private function sortedPermissions(Role $role): array
    {
        $permissions = (array) $role->getRawPermissions();
        ksort($permissions);

        return array_map(static function ($names): array {
            $names = array_values((array) $names);
            sort($names);

            return $names;
        }, $permissions);
    }
}
