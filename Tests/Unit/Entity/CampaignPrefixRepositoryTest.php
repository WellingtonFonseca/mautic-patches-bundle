<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Entity;

use Doctrine\ORM\QueryBuilder;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixRepository;
use PHPUnit\Framework\TestCase;

class CampaignPrefixRepositoryTest extends TestCase
{
    private function repository(): CampaignPrefixRepository
    {
        return (new \ReflectionClass(CampaignPrefixRepository::class))->newInstanceWithoutConstructor();
    }

    private function build(CampaignPrefixRepository $repository, QueryBuilder $qb, array $args): void
    {
        (new \ReflectionMethod($repository, 'buildOrderByClause'))->invoke($repository, $qb, $args);
    }

    public function testItIsCoresCampaignRepository(): void
    {
        $this->assertInstanceOf(CampaignRepository::class, $this->repository());
    }

    public function testSortingByPrefixSortsOnAHiddenSubSelectThenByName(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('addSelect')->with($this->callback(
            fn (string $dql) => str_contains($dql, 'SELECT sp.prefix FROM MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixValue sp WHERE sp.campaignId = c.id')
                && str_ends_with($dql, 'AS HIDDEN campaign_prefix')
        ));
        $calls = [];
        $qb->method('addOrderBy')->willReturnCallback(function (string $sort, string $dir) use (&$calls, $qb) {
            $calls[] = [$sort, $dir];

            return $qb;
        });

        $this->build($this->repository(), $qb, ['orderBy' => 'campaign_prefix', 'orderByDir' => 'DESC']);

        $this->assertSame([['campaign_prefix', 'DESC'], ['c.name', 'ASC']], $calls);
    }

    public function testADirectionThatIsNotDescendingIsAscending(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('addSelect')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('addOrderBy')->willReturnSelf();
        $qb->expects($this->atLeastOnce())->method('addOrderBy')->with($this->anything(), $this->logicalNot($this->equalTo('DESC')));

        $this->build($this->repository(), $qb, ['orderBy' => 'campaign_prefix', 'orderByDir' => 'anything; drop']);
    }

    public function testOtherOrdersAreCoresOwn(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('addSelect');
        $qb->expects($this->once())->method('addOrderBy')->with('c.name', 'ASC');

        $this->build($this->repository(), $qb, ['orderBy' => 'c.name', 'orderByDir' => 'ASC']);
    }
}
