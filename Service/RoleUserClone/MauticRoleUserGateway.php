<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Model\RoleModel;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The gateway of this Mautic instance: core's models save (the role model also rebuilds the permission rows), core's validator checks.
 * The user model saves the hash as it is: only a plain password set through the form gets hashed.
 */
class MauticRoleUserGateway implements RoleUserGateway
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleModel $roleModel,
        private UserModel $userModel,
        private CorePermissions $security,
        private ValidatorInterface $validator
    ) {
    }

    public function findRoleByName(string $name): ?Role
    {
        return $this->em->getRepository(Role::class)->findOneBy(['name' => $name]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findUserByUsername(string $username): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    public function unknownPermissions(array $permissions): array
    {
        $objects = $this->security->getPermissionObjects();
        $unknown = [];
        foreach ($permissions as $key => $levels) {
            $parts  = explode(':', $key);
            $object = 2 === count($parts) ? ($objects[$parts[0]] ?? null) : null;
            foreach ($levels as $level) {
                if (null === $object || !is_string($level) || !$object->isSupported($parts[1], $level)) {
                    $unknown[] = $key.':'.(is_string($level) ? $level : '?');
                }
            }
        }

        return $unknown;
    }

    public function validate(object $entity): array
    {
        // A user holds only the hash: core's class validator would measure the strength of plainPassword (null here) and fail with a
        // TypeError. The properties are checked one by one; the username and e-mail are unique by the importer's own lookups.
        $violations = $entity instanceof User ? $this->userViolations($entity) : $this->validator->validate($entity);

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    /**
     * @return iterable<\Symfony\Component\Validator\ConstraintViolationInterface>
     */
    private function userViolations(User $user): iterable
    {
        foreach (['username', 'email', 'firstName', 'lastName'] as $property) {
            yield from $this->validator->validateProperty($user, $property);
        }
    }

    public function saveRole(Role $role, array $permissions): void
    {
        $this->roleModel->setRolePermissions($role, $permissions);
        $this->roleModel->saveEntity($role);
    }

    public function saveUser(User $user): void
    {
        $this->userModel->saveEntity($user);
    }
}
