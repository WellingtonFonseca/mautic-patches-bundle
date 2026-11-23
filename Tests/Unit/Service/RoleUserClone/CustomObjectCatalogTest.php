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
}
