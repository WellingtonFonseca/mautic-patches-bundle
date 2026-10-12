<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;

class SegmentPrefixStoreTest extends TestCase
{
    private function store(array $rows = [], ?Connection $connection = null): SegmentPrefixStore
    {
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getArrayResult'])->getMock();
        $query->method('getArrayResult')->willReturn($rows);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('mt_segment_prefix');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);
        $em->method('getClassMetadata')->with(SegmentPrefixValue::class)->willReturn($metadata);
        $em->method('getConnection')->willReturn($connection ?? $this->createMock(Connection::class));

        return new SegmentPrefixStore($em);
    }

    public function testAllMapsTheSegmentIdToItsPrefix(): void
    {
        $store = $this->store([['segmentId' => '1', 'prefix' => 'Compras'], ['segmentId' => 2, 'prefix' => 'Vendas']]);

        $this->assertSame([1 => 'Compras', 2 => 'Vendas'], $store->all());
        $this->assertSame('Vendas', $store->get(2));
        $this->assertNull($store->get(3));
        $this->assertNull($store->get(null));
    }

    public function testSaveIsAnUpsertOnThePrefixedTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with(
            $this->stringContains('INSERT INTO mt_segment_prefix (segment_id, prefix) VALUES (?, ?) ON DUPLICATE KEY UPDATE'),
            [5, 'Compras']
        );

        $this->store([], $connection)->save(5, 'Compras');
    }

    public function testRemoveDeletesTheRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with('DELETE FROM mt_segment_prefix WHERE segment_id = ?', [5]);

        $this->store([], $connection)->remove(5);
    }

    public function testRememberedPrefixIsPerSegmentObject(): void
    {
        $store = $this->store();
        $one   = new LeadList();
        $other = new LeadList();

        $store->remember($one, 'Compras');

        $this->assertSame('Compras', $store->pending($one));
        $this->assertNull($store->pending($other));
    }
}
