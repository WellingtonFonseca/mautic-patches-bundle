<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Mautic\UserBundle\Entity\User;

/**
 * A user as the clone package holds it. The password is the bcrypt hash: it carries algorithm, cost and salt, so the same password
 * works on the other instance. The role is named, not numbered. The id is only the id here, so the failure list of an import points back to the user on this instance.
 */
class UserSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        return [
            'id'           => (int) $user->getId(),
            'username'     => (string) $user->getUsername(),
            'email'        => (string) $user->getEmail(),
            'firstName'    => (string) $user->getFirstName(),
            'lastName'     => (string) $user->getLastName(),
            'position'     => (string) $user->getPosition(),
            'locale'       => (string) $user->getLocale(),
            'timezone'     => (string) $user->getTimezone(),
            'isPublished'  => (bool) $user->isPublished(),
            'passwordHash' => (string) $user->getPassword(),
            'role'         => (string) $user->getRole()?->getName(),
        ];
    }
}
