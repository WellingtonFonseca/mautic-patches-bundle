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

    public function testAnAliasWithTextAroundAValidOneIsRefused(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        foreach (['!email', 'email!', "email\n!"] as $alias) {
            try {
                $this->values->find('lead', $alias, '');
                $this->fail($alias);
            } catch (\OutOfBoundsException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheFieldIsLookedUpByObjectAndAlias(): void
    {
        $this->connection->expects($this->once())->method('fetchOne')
            ->with($this->stringContains('FROM lead_fields WHERE object = :object AND alias = :alias AND is_published = 1'), ['object' => 'company', 'alias' => 'companyname'])
            ->willReturn('text');
        $this->connection->method('fetchFirstColumn')->willReturn([]);

        $this->values->find('company', 'companyname', '');
    }

    public function testTheLimitIsAtLeastOneAndTheValuesAreStrings(): void
    {
        $this->connection->method('fetchOne')->willReturn('text');
        $this->connection->expects($this->exactly(2))->method('fetchFirstColumn')
            ->willReturnCallback(function (string $sql): array {
                $this->assertMatchesRegularExpression('/LIMIT 1$/', $sql);

                return [5, 'a'];
            });

        $this->assertSame(['5', 'a'], $this->values->find('lead', 'email', '', 0));
        $this->assertSame(['5', 'a'], $this->values->find('lead', 'email', '', -3));
    }

    public function testTheQueryIsBuiltOnTheRightTableInTheRightOrder(): void
    {
        $this->connection->method('fetchOne')->willReturn('text');
        $this->connection->expects($this->once())->method('fetchFirstColumn')
            ->with("SELECT DISTINCT `email` FROM leads WHERE `email` LIKE :search AND `email` <> '' ORDER BY `email` LIMIT 20", $this->anything())
            ->willReturn([]);

        $this->values->find('lead', 'email', '');
    }
}
