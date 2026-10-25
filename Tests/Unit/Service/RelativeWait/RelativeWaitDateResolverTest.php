<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RelativeWait;

use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RelativeWaitDateResolverTest extends TestCase
{
    private const ZONE = 'America/Sao_Paulo';

    /**
     * @param array<string, mixed> $answers  a part of the SQL => what fetchOne answers (a string, or a list of rows for fetchAssociative)
     */
    private function resolver(array $answers, ?array &$queries = null): RelativeWaitDateResolver
    {
        $queries    = [];
        $connection = $this->createMock(Connection::class);
        $answer     = function (string $sql, array $params = [], array $types = []) use ($answers, &$queries) {
            $queries[] = [$sql, $params];
            foreach ($answers as $part => $value) {
                if (str_contains($sql, (string) $part)) {
                    return $value;
                }
            }

            return false;
        };
        $connection->method('fetchOne')->willReturnCallback($answer);
        $connection->method('fetchAssociative')->willReturnCallback($answer);

        return new RelativeWaitDateResolver($connection, self::ZONE);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function config(array $override = []): array
    {
        return array_merge(['dateField' => 'contact:attribution_date', 'amount' => 12, 'unit' => 'd', 'direction' => 'after'], $override);
    }

    private function utc(?\DateTimeInterface $date): ?string
    {
        return $date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function testAContactDatetimeFieldIsStoredInUtcAndTheOffsetIsAddedToIt(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 15:30:00']);

        $this->assertSame('2026-03-13 15:30:00', $this->utc($resolver->resolve(7, $this->config())));
    }

    public function testAContactDateFieldIsADayOfTheSystemTimeZoneNotAnInstantInUtc(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'date', 'FROM leads' => '2026-03-01']);
        $date     = $resolver->resolve(7, $this->config());

        $this->assertSame('2026-03-13 00:00:00', $date->setTimezone(new \DateTimeZone(self::ZONE))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-13 03:00:00', $this->utc($date), 'midnight in São Paulo is 03:00 UTC: a date field never moves to the day before');
    }

    public function testBeforeSubtractsTheOffset(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-20 10:00:00']);

        $this->assertSame('2026-03-08 10:00:00', $this->utc($resolver->resolve(7, $this->config(['direction' => 'before']))));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function units(): array
    {
        return [
            'minutes' => ['i', 90, '2026-03-01 11:30:00'],
            'hours'   => ['h', 5, '2026-03-01 15:00:00'],
            'days'    => ['d', 3, '2026-03-04 10:00:00'],
            'weeks'   => ['w', 2, '2026-03-15 10:00:00'],
            'months'  => ['m', 1, '2026-04-01 10:00:00'],
            'years'   => ['y', 1, '2027-03-01 10:00:00'],
            'zero'    => ['d', 0, '2026-03-01 10:00:00'],
        ];
    }

    #[DataProvider('units')]
    public function testEveryUnitOfTheOtherWaitsIsUnderstood(string $unit, int $amount, string $expected): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 10:00:00']);

        $this->assertSame($expected, $this->utc($resolver->resolve(7, $this->config(['unit' => $unit, 'amount' => $amount]))));
    }

    public function testTheContactWithoutTheDateHasNoDate(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => null]);

        $this->assertNull($resolver->resolve(7, $this->config()));
    }

    public function testAFieldThatIsNotADateHasNoDate(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM lead_fields' => 'text', 'FROM leads' => '2026-03-01 10:00:00'], $queries);

        $this->assertNull($resolver->resolve(7, $this->config(['dateField' => 'contact:firstname'])));
        $this->assertCount(0, array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'FROM leads')), 'the value is not even read');
    }

    public function testACustomFieldThatIsNotJustDigitsIsNotLookedUp(): void
    {
        foreach (['a33', '33a'] as $id) {
            $queries = [];
            $date    = $this->resolver(['FROM custom_field' => 'date'], $queries)->resolve(7, $this->config(['dateField' => 'custom_object:'.$id]));

            $this->assertNull($date);
            $this->assertSame([], $queries, $id);
        }
    }

    public function testAZeroOrEmptyDateIsNoDate(): void
    {
        foreach (['0000-00-00 00:00:00', ''] as $value) {
            $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => $value]);

            $this->assertNull($resolver->resolve(7, $this->config()), var_export($value, true));
        }
    }

    public function testADateFieldKeepsOnlyTheDayOfWhatIsStored(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'date', 'FROM leads' => '2026-03-01 23:00:00']);
        $date     = $resolver->resolve(7, $this->config(['amount' => 0]));

        $this->assertSame('2026-03-01 00:00:00', $date->setTimezone(new \DateTimeZone(self::ZONE))->format('Y-m-d H:i:s'));
    }

    public function testAFractionalAmountBelowOneIsZeroAndAboveOneIsTruncated(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 10:00:00']);

        $this->assertSame('2026-03-01 10:00:00', $this->utc($resolver->resolve(7, $this->config(['amount' => '-0.5']))));
        $this->assertSame('2026-03-02 10:00:00', $this->utc($resolver->resolve(7, $this->config(['amount' => '1.9']))));
    }

    public function testTheQueriesOfAContactFieldCarryTheAliasAndTheContact(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 10:00:00'], $queries);
        $resolver->resolve(7, $this->config());

        $this->assertSame(['object' => 'lead', 'alias' => 'attribution_date'], $queries[0][1]);
        $this->assertStringContainsString('`attribution_date`', $queries[1][0]);
        $this->assertSame(['id' => 7], $queries[1][1]);
    }

    public function testEachFieldHasItsOwnCachedType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params = []) {
            if (str_contains($sql, 'FROM lead_fields')) {
                return 'birth' === $params['alias'] ? 'datetime' : 'text';
            }
            if (str_contains($sql, 'FROM custom_field')) {
                return 'text';
            }

            return '2026-03-01 10:00:00';
        });
        $connection->method('fetchAssociative')->willReturn(['value' => '2026-03-01 10:00:00']);
        $resolver = new RelativeWaitDateResolver($connection, self::ZONE);

        $this->assertNotNull($resolver->resolve(7, $this->config(['dateField' => 'contact:birth'])));
        $this->assertNull($resolver->resolve(7, $this->config(['dateField' => 'contact:other'])), 'another alias is not answered with the cached type of birth');
        $this->assertNull($resolver->resolve(7, $this->config(['dateField' => 'custom_object:5'])));
        $this->assertNull($resolver->resolve(7, $this->config(['dateField' => 'custom_object:6'])));
    }

    public function testTheTypeQueryOfACustomFieldCarriesItsId(): void
    {
        $queries = [];
        $this->resolver(['FROM custom_field' => 'date'], $queries)->resolve(7, $this->config(['dateField' => 'custom_object:33']));

        $this->assertSame(['id' => 33], $queries[0][1]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function brokenConfigs(): array
    {
        return [
            'no field'            => [['dateField' => '']],
            'unknown source'      => [['dateField' => 'company:founded']],
            'text before source'  => [['dateField' => 'zzcontact:attribution_date']],
            'text after alias'    => [['dateField' => 'contact:attribution_date!']],
            'text before id'      => [['dateField' => 'custom_object:a33']],
            'text after id'       => [['dateField' => 'custom_object:33a']],
            'alias with an SQL'   => [['dateField' => 'contact:a`; DROP TABLE leads; --']],
            'custom field not id' => [['dateField' => 'custom_object:abc']],
            'unknown unit'        => [['unit' => 'x']],
            'negative amount'     => [['amount' => -1]],
            'unknown direction'   => [['direction' => 'around']],
        ];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('brokenConfigs')]
    public function testABrokenSettingHasNoDateAndReadsNothingDangerous(array $override): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 10:00:00'], $queries);

        $this->assertNull($resolver->resolve(7, $this->config($override)));
        foreach ($queries as [$sql]) {
            $this->assertStringNotContainsString('DROP', $sql);
        }
    }

    public function testACustomObjectFieldTakesTheItemWithTheLatestDateAndTheHighestIdOnATie(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM custom_field' => 'date', 'custom_item_xref_contact' => ['value' => '2026-03-05 00:00:00']], $queries);
        $date     = $resolver->resolve(7, $this->config(['dateField' => 'custom_object:33']));

        $this->assertSame('2026-03-17 00:00:00', $date->setTimezone(new \DateTimeZone(self::ZONE))->format('Y-m-d H:i:s'));

        $item = array_values(array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'custom_item_xref_contact')));
        $this->assertCount(1, $item);
        $this->assertStringContainsString('ORDER BY v.value DESC, i.id DESC', $item[0][0]);
        $this->assertStringContainsString('LIMIT 1', $item[0][0]);
        $this->assertStringContainsString('custom_field_value_date', $item[0][0]);
        $this->assertSame(7, $item[0][1]['contact']);
        $this->assertSame(33, $item[0][1]['field']);
    }

    public function testACustomObjectDatetimeFieldReadsItsOwnTableAndIsAnInstant(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM custom_field' => 'datetime', 'custom_item_xref_contact' => ['value' => '2026-03-05 18:00:00']], $queries);
        $date     = $resolver->resolve(7, $this->config(['dateField' => 'custom_object:35']));

        $this->assertSame('2026-03-17 18:00:00', $this->utc($date));
        $item = array_values(array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'custom_item_xref_contact')));
        $this->assertStringContainsString('custom_field_value_datetime', $item[0][0]);
    }

    public function testACustomObjectFieldThatIsNotADateOrDoesNotExistHasNoDate(): void
    {
        $this->assertNull($this->resolver(['FROM custom_field' => 'text'])->resolve(7, $this->config(['dateField' => 'custom_object:40'])));
        $this->assertNull($this->resolver([])->resolve(7, $this->config(['dateField' => 'custom_object:999'])));
    }

    public function testAContactWithoutItemsHasNoDate(): void
    {
        $resolver = $this->resolver(['FROM custom_field' => 'date', 'custom_item_xref_contact' => false]);

        $this->assertNull($resolver->resolve(7, $this->config(['dateField' => 'custom_object:33'])));
    }

    public function testOnlyTheItemsTheSegmentsSelectAreConsidered(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM custom_field' => 'date', 'custom_item_xref_contact' => ['value' => '2026-03-05 00:00:00']], $queries);
        $resolver->resolve(7, $this->config(['dateField' => 'custom_object:33']), [5, 6]);

        $item = array_values(array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'custom_item_xref_contact')));
        $this->assertStringContainsString('i.id IN (:items)', $item[0][0]);
        $this->assertSame([5, 6], $item[0][1]['items']);
        $this->assertStringContainsString('ORDER BY v.value DESC, i.id DESC', $item[0][0], 'among the selected ones, the same rule');
    }

    public function testWhenTheSegmentsSelectNoItemThereIsNoDateAndNothingIsRead(): void
    {
        $queries = [];
        $date    = $this->resolver(['FROM custom_field' => 'date'], $queries)->resolve(7, $this->config(['dateField' => 'custom_object:33']), []);

        $this->assertNull($date);
        $this->assertSame([], array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'custom_item_xref_contact')));
    }

    public function testTheSelectedItemsMeanNothingToAContactField(): void
    {
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 15:30:00']);

        $this->assertSame('2026-03-13 15:30:00', $this->utc($resolver->resolve(7, $this->config(), [5])));
    }

    public function testTheTypeOfAFieldIsLookedUpOnceForAWholeBatch(): void
    {
        $queries  = [];
        $resolver = $this->resolver(['FROM lead_fields' => 'datetime', 'FROM leads' => '2026-03-01 15:30:00'], $queries);
        foreach ([1, 2, 3] as $contact) {
            $resolver->resolve($contact, $this->config());
        }

        $this->assertCount(1, array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'FROM lead_fields')));
        $this->assertCount(3, array_filter($queries, static fn (array $q): bool => str_contains($q[0], 'FROM leads')), 'the value is per contact');
    }
}
