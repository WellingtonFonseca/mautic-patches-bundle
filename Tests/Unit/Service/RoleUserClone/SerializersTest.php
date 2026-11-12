<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleSerializer;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\UserSerializer;
use PHPUnit\Framework\TestCase;

class SerializersTest extends TestCase
{
    public function testARoleKeepsNameDescriptionAdminFlagAndSortedPermissions(): void
    {
        $role = new Role();
        $role->setName('Gestão');
        $role->setDescription('Equipe');
        $role->setIsAdmin(false);
        $role->setRawPermissions(['user:users' => ['view', 'create'], 'lead:leads' => ['full']]);

        $this->assertSame(
            ['id' => 0, 'name' => 'Gestão', 'description' => 'Equipe', 'isAdmin' => false, 'permissions' => ['lead:leads' => ['full'], 'user:users' => ['create', 'view']]],
            (new RoleSerializer())->toArray($role)
        );
    }

    public function testAnAdminRoleHasNoPermissionsInThePackage(): void
    {
        $role = new Role();
        $role->setName('Administrator');
        $role->setIsAdmin(true);
        $role->setRawPermissions(['lead:leads' => ['full']]);

        $data = (new RoleSerializer())->toArray($role);

        $this->assertTrue($data['isAdmin']);
        $this->assertSame([], $data['permissions']);
    }

    public function testTheIdOfThisInstanceTravelsInThePackage(): void
    {
        $role = new Role();
        $user = new User();
        $this->setId($role, 7);
        $this->setId($user, 9);

        $this->assertSame(7, (new RoleSerializer())->toArray($role)['id']);
        $this->assertSame(9, (new UserSerializer())->toArray($user)['id']);
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }

    public function testARoleWithoutDescriptionGivesAnEmptyText(): void
    {
        $role = new Role();
        $role->setName('R');

        $this->assertSame('', (new RoleSerializer())->toArray($role)['description']);
    }

    public function testAUserKeepsEveryFieldAndNamesTheRole(): void
    {
        $role = new Role();
        $role->setName('Gestão');
        $user = new User();
        $user->setUsername('ana');
        $user->setEmail('ana@example.com');
        $user->setFirstName('Ana');
        $user->setLastName('Souza');
        $user->setPosition('Chefe');
        $user->setLocale('pt_BR');
        $user->setTimezone('America/Sao_Paulo');
        $user->setIsPublished(false);
        $user->setPassword('$2y$13$hash');
        $user->setRole($role);

        $this->assertSame(
            [
                'id'           => 0,
                'username'     => 'ana',
                'email'        => 'ana@example.com',
                'firstName'    => 'Ana',
                'lastName'     => 'Souza',
                'position'     => 'Chefe',
                'locale'       => 'pt_BR',
                'timezone'     => 'America/Sao_Paulo',
                'isPublished'  => false,
                'passwordHash' => '$2y$13$hash',
                'role'         => 'Gestão',
            ],
            (new UserSerializer())->toArray($user)
        );
    }

    public function testAUserWithoutRoleHasAnEmptyRoleName(): void
    {
        $this->assertSame('', (new UserSerializer())->toArray(new User())['role']);
    }

    public function testARoleWithoutNameGivesAnEmptyNameText(): void
    {
        $this->assertSame('', (new RoleSerializer())->toArray(new Role())['name']);
    }

    public function testAnEmptyUserGivesTextsAndABooleanNeverNull(): void
    {
        $data = (new UserSerializer())->toArray(new User());

        foreach (['username', 'email', 'firstName', 'lastName', 'position', 'locale', 'timezone', 'passwordHash', 'role'] as $key) {
            $this->assertSame('', $data[$key], $key);
        }
        $this->assertIsBool($data['isPublished']);
    }
}
