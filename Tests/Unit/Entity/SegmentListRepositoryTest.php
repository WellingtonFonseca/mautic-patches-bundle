<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Entity;

use Doctrine\ORM\QueryBuilder;
use Mautic\LeadBundle\Entity\LeadListRepository;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentListRepository;
use PHPUnit\Framework\TestCase;

class SegmentListRepositoryTest extends TestCase
{
    private function repository(): SegmentListRepository
    {
        return (new \ReflectionClass(SegmentListRepository::class))->newInstanceWithoutConstructor();
    }

    private function build(SegmentListRepository $repository, QueryBuilder $qb, array $args): void
    {
        (new \ReflectionMethod($repository, 'buildOrderByClause'))->invoke($repository, $qb, $args);
    }

    public function testItIsCoresSegmentRepository(): void
    {
        $this->assertInstanceOf(LeadListRepository::class, $this->repository());
    }

    public function testSortingByPrefixSortsOnAHiddenSubSelectThenByName(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('addSelect')->with($this->callback(
            fn (string $dql) => str_contains($dql, 'SELECT sp.prefix FROM MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue sp WHERE sp.segmentId = l.id')
                && str_ends_with($dql, 'AS HIDDEN segment_prefix')
        ));
        $calls = [];
        $qb->method('addOrderBy')->willReturnCallback(function (string $sort, string $dir) use (&$calls, $qb) {
            $calls[] = [$sort, $dir];

            return $qb;
        });

        $this->build($this->repository(), $qb, ['orderBy' => 'segment_prefix', 'orderByDir' => 'DESC']);

        $this->assertSame([['segment_prefix', 'DESC'], ['l.name', 'ASC']], $calls);
    }

    public function testADirectionThatIsNotDescendingIsAscending(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('addSelect')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('addOrderBy')->willReturnSelf();
        $qb->expects($this->atLeastOnce())->method('addOrderBy')->with($this->anything(), $this->logicalNot($this->equalTo('DESC')));

        $this->build($this->repository(), $qb, ['orderBy' => 'segment_prefix', 'orderByDir' => 'anything; drop']);
    }

    public function testOtherOrdersAreCoresOwn(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('addSelect');
        $qb->expects($this->once())->method('addOrderBy')->with('l.name', 'ASC');

        $this->build($this->repository(), $qb, ['orderBy' => 'l.name', 'orderByDir' => 'ASC']);
    }
}
