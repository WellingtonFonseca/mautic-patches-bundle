<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Service\IndexedLeadFields;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctQuery;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('area:segment')]
#[Group('risk:CRITICO')]
#[Group('risk:DADOS')]
class SegmentDistinctQueryTest extends TestCase
{
    /** @var list<string> conditions added to the pool */
    private array $poolWhere = [];

    /** @var list<string> conditions added to the segment query */
    private array $mainWhere = [];

    /**
     * @param array<string, bool> $columns field => is a text column
     */
    private function apply(SegmentDistinctConfig $config, array $columns): void
    {
        if (!defined('MAUTIC_TABLE_PREFIX')) {
            define('MAUTIC_TABLE_PREFIX', '');
        }

        $store = $this->createMock(SegmentDistinctStore::class);
        $store->method('get')->willReturn($config);

        $indexed = $this->createMock(IndexedLeadFields::class);
        $indexed->method('columnExists')->willReturnCallback(static fn (string $c): bool => isset($columns[$c]));
        $indexed->method('isTextColumn')->willReturnCallback(static fn (string $c): bool => $columns[$c] ?? false);

        $pool = $this->createMock(QueryBuilder::class);
        $pool->method('getTableAlias')->willReturn('l');
        $pool->method('andWhere')->willReturnCallback(function ($w) use ($pool) {
            $this->poolWhere[] = (string) $w;

            return $pool;
        });
        $pool->method('getSQL')->willReturn('POOL_SQL');
        $pool->method('getParameters')->willReturn([]);
        $pool->method('getParameterTypes')->willReturn([]);

        $builder = $this->createMock(ContactSegmentQueryBuilder::class);
        $builder->method('assembleContactsSegmentQueryBuilder')->willReturn($pool);

        $main = $this->createMock(QueryBuilder::class);
        $main->method('getTableAlias')->willReturn('m');
        $main->method('getParameters')->willReturn([]);
        $main->method('getParameterTypes')->willReturn([]);
        $main->method('andWhere')->willReturnCallback(function ($w) use ($main) {
            $this->mainWhere[] = (string) $w;

            return $main;
        });

        $segment = $this->createMock(LeadList::class);
        $segment->method('getId')->willReturn(7);

        $query = new SegmentDistinctQuery($store, $indexed, $builder, $this->createMock(ContactSegmentFilterFactory::class), new NullLogger());
        $query->apply($segment, $main);
    }

    public function testNumericFieldIsNotComparedToEmptyString(): void
    {
        $this->apply(new SegmentDistinctConfig(true, true, ['score']), ['score' => false]);

        $pool = implode(' | ', $this->poolWhere);
        $this->assertStringContainsString('l.score IS NOT NULL', $pool);
        $this->assertStringNotContainsString("<> ''", $pool, 'a numeric 0 must stay in the pool');
        $this->assertStringNotContainsString("<> ''", $this->mainWhere[0]);
        $this->assertStringNotContainsString("= ''", $this->mainWhere[0]);
    }

    public function testTextFieldIsComparedToEmptyString(): void
    {
        $this->apply(new SegmentDistinctConfig(true, true, ['city']), ['city' => true]);

        $this->assertStringContainsString("l.city <> ''", implode(' | ', $this->poolWhere));
        $this->assertStringContainsString("m.city <> ''", $this->mainWhere[0]);
    }

    public function testRemoveEmptyOffKeepsEmptyTextButNeverTreatsNumericZeroAsEmpty(): void
    {
        $this->apply(new SegmentDistinctConfig(true, false, ['city', 'score']), ['city' => true, 'score' => false]);

        $where = $this->mainWhere[0];
        $this->assertStringContainsString("m.city = ''", $where);
        $this->assertStringContainsString('m.score IS NULL', $where);
        $this->assertStringNotContainsString("m.score = ''", $where);
    }

    public function testDisabledConfigAddsNothing(): void
    {
        $this->apply(new SegmentDistinctConfig(false, true, ['city']), ['city' => true]);

        $this->assertSame([], $this->mainWhere);
    }
}
