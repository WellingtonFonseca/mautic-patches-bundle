<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\Service\SegmentFilterValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SegmentFilterValuesTest extends TestCase
{
    private Connection&MockObject $connection;
    private SegmentFilterValues $values;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->values     = new SegmentFilterValues($this->connection);
    }

    public function testAContactTextFieldGivesItsDistinctValuesThatMatchTheSearch(): void
    {
        $this->connection->method('fetchOne')->willReturn('text');
        $this->connection->expects($this->once())->method('fetchFirstColumn')
            ->with(
                $this->callback(static fn (string $sql) => str_contains($sql, 'DISTINCT `inst_alias`') && str_contains($sql, 'leads') && str_contains($sql, 'LIKE') && str_contains($sql, 'LIMIT 20')),
                ['search' => '%ab%']
            )
            ->willReturn(['abc', 'zab']);

        $this->assertSame(['abc', 'zab'], $this->values->find('lead', 'inst_alias', 'ab'));
    }

    public function testACompanyFieldReadsTheCompaniesTable(): void
    {
        $this->connection->method('fetchOne')->willReturn('text');
        $this->connection->expects($this->once())->method('fetchFirstColumn')
            ->with($this->callback(static fn (string $sql) => str_contains($sql, 'companies') && str_contains($sql, '`companyname`')), $this->anything())
            ->willReturn([]);

        $this->values->find('company', 'companyname', '');
    }

    public function testLikeWildcardsAreEscapedAndTheLimitIsCapped(): void
    {
        $this->connection->method('fetchOne')->willReturn('email');
        $this->connection->expects($this->once())->method('fetchFirstColumn')
            ->with($this->callback(static fn (string $sql) => str_contains($sql, 'LIMIT 50')), ['search' => '%a\%\_b%'])
            ->willReturn([]);

        $this->values->find('lead', 'email', 'a%_b', 9999);
    }

    #[DataProvider('notTextTypes')]
    public function testAFieldThatIsNotTextIsRefused(?string $type): void
    {
        $this->connection->method('fetchOne')->willReturn($type ?? false);
        $this->connection->expects($this->never())->method('fetchFirstColumn');

        $this->expectException(\OutOfBoundsException::class);
        $this->values->find('lead', 'points', '');
    }

    public static function notTextTypes(): array
    {
        return ['number' => ['number'], 'date' => ['date'], 'select' => ['select'], 'lookup' => ['lookup'], 'not a field' => [null]];
    }

    public function testAnObjectThatIsNotContactOrCompanyIsRefused(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $this->expectException(\OutOfBoundsException::class);
        $this->values->find('custom_object', 'cmf_1', '');
    }

    public function testAnAliasWithSymbolsNeverReachesTheQuery(): void
    {
        $this->connection->expects($this->never())->method('fetchFirstColumn');

        $this->expectException(\OutOfBoundsException::class);
        $this->values->find('lead', 'a`; DROP TABLE leads;--', '');
    }
}
