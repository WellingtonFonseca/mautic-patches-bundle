<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixValue;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CampaignPrefixStoreTest extends TestCase
{
    private function store(array $rows = [], ?Connection $connection = null): CampaignPrefixStore
    {
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getArrayResult'])->getMock();
        $query->method('getArrayResult')->willReturn($rows);

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('mt_campaign_prefix');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);
        $em->method('getClassMetadata')->with(CampaignPrefixValue::class)->willReturn($metadata);
        $em->method('getConnection')->willReturn($connection ?? $this->createMock(Connection::class));

        return new CampaignPrefixStore($em);
    }

    public function testAllMapsTheCampaignIdToItsPrefix(): void
    {
        $store = $this->store([['campaignId' => '1', 'prefix' => 'Compras'], ['campaignId' => 2, 'prefix' => 'Vendas']]);

        $this->assertSame([1 => 'Compras', 2 => 'Vendas'], $store->all());
        $this->assertSame('Vendas', $store->get(2));
        $this->assertNull($store->get(3));
        $this->assertNull($store->get(null));
    }

    public function testSaveIsAnUpsertOnThePrefixedTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with(
            $this->stringContains('INSERT INTO mt_campaign_prefix (campaign_id, prefix) VALUES (?, ?) ON DUPLICATE KEY UPDATE'),
            [5, 'Compras']
        );

        $this->store([], $connection)->save(5, 'Compras');
    }

    public function testRemoveDeletesTheRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with('DELETE FROM mt_campaign_prefix WHERE campaign_id = ?', [5]);

        $this->store([], $connection)->remove(5);
    }

    public function testRememberedPrefixIsPerCampaignObject(): void
    {
        $store = $this->store();
        $one   = new Campaign();
        $other = new Campaign();

        $store->remember($one, 'Compras');

        $this->assertSame('Compras', $store->pending($one));
        $this->assertNull($store->pending($other));
    }
}
