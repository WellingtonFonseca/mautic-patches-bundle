<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\CustomObjectCatalog;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:users-roles')]
#[Group('risk:DADOS')]
class CustomObjectCatalogTest extends TestCase
{
    public function testWithoutTheCustomObjectsPluginThereAreNoObjectsAndNoLookup(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');

        $catalog = new CustomObjectCatalog($em);

        if (class_exists('MauticPlugin\\CustomObjectsBundle\\Entity\\CustomObject')) {
            $this->markTestSkipped('Custom Objects is installed here.');
        }
        $this->assertSame([], $catalog->names());
        $this->assertNull($catalog->idByName('Pedidos'));
    }

    public function testWithTheCustomObjectsPluginNamesComeFromTheRepositoryOnceAndResolveById(): void
    {
        if (!class_exists('MauticPlugin\\CustomObjectsBundle\\Entity\\CustomObject')) {
            $this->markTestSkipped('Custom Objects is not installed here.');
        }
        $pedidos = new class() {
            public function getId(): int
            {
                return 3;
            }

            public function getName(): string
            {
                return 'Pedidos';
            }
        };
        $outro = new class() {
            public function getId(): int
            {
                return 4;
            }

            public function getName(): string
            {
                return 'Outro';
            }
        };
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())->method('findAll')->willReturn([$pedidos, $outro]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->willReturn($repository);

        $catalog = new CustomObjectCatalog($em);

        $this->assertSame([3 => 'Pedidos', 4 => 'Outro'], $catalog->names());
        $this->assertSame(3, $catalog->idByName('Pedidos'));
        $this->assertNull($catalog->idByName('Nada'));
    }
}
