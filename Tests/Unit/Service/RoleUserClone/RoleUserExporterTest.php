<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\ClonePackage;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleSerializer;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\RoleUserExporter;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\UserSerializer;
use PHPUnit\Framework\TestCase;

class RoleUserExporterTest extends TestCase
{
    private function exporter(): RoleUserExporter
    {
        return new RoleUserExporter(new ClonePackage(), new RoleSerializer(), new UserSerializer());
    }

    private function role(string $name): Role
    {
        $role = new Role();
        $role->setName($name);

        return $role;
    }

    private function user(string $username, Role $role): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username.'@example.com');
        $user->setRole($role);

        return $user;
    }

    public function testTheRolesPackageOpensWithItsKeyAndHoldsOnlyRoles(): void
    {
        $export = $this->exporter()->exportRoles([$this->role('B'), $this->role('A')]);
        $data   = (new ClonePackage())->decrypt($export->getPackage(), $export->getKey());

        $this->assertSame('roles', $data['kind']);
        $this->assertSame(['A', 'B'], array_column($data['roles'], 'name'));
        $this->assertArrayNotHasKey('users', $data);
    }

    public function testTheUsersPackageCarriesEachRoleInUseOnlyOnce(): void
    {
        $manager = $this->role('Gestão');
        $other   = $this->role('Outra');
        $export  = $this->exporter()->exportUsers([$this->user('ana', $manager), $this->user('bia', $manager)]);
        $data    = (new ClonePackage())->decrypt($export->getPackage(), $export->getKey());

        $this->assertSame('users', $data['kind']);
        $this->assertSame(['Gestão'], array_column($data['roles'], 'name'));
        $this->assertSame(['ana', 'bia'], array_column($data['users'], 'username'));
        $this->assertNotContains($other->getName(), array_column($data['roles'], 'name'));
    }

    public function testAUserWithoutRoleAddsNoRole(): void
    {
        $user   = new User();
        $user->setUsername('sem');
        $export = $this->exporter()->exportUsers([$user]);
        $data   = (new ClonePackage())->decrypt($export->getPackage(), $export->getKey());

        $this->assertSame([], $data['roles']);
        $this->assertSame(['sem'], array_column($data['users'], 'username'));
    }

    public function testNothingChosenIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->exporter()->exportUsers([]);
    }

    public function testEveryExportGetsItsOwnKey(): void
    {
        $role = $this->role('A');

        $this->assertNotSame($this->exporter()->exportRoles([$role])->getKey(), $this->exporter()->exportRoles([$role])->getKey());
    }

    public function testNoRolesChosenIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->exporter()->exportRoles([]);
    }

    public function testTheUsersComeSortedByUsername(): void
    {
        $role   = $this->role('R');
        $export = $this->exporter()->exportUsers([$this->user('zeca', $role), $this->user('ana', $role), $this->user('mara', $role)]);
        $data   = (new ClonePackage())->decrypt($export->getPackage(), $export->getKey());

        $this->assertSame(['ana', 'mara', 'zeca'], array_column($data['users'], 'username'));
    }
}
