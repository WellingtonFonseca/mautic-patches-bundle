<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentSourceNames;
use PHPUnit\Framework\TestCase;

class SegmentSourceNamesTest extends TestCase
{
    public function testAPrefixedSegmentShowsPrefixDashName(): void
    {
        $this->assertSame([3 => 'Compras - Onboarding'], SegmentSourceNames::apply([3 => 'Onboarding'], [3 => 'Compras']));
    }

    public function testASegmentWithoutPrefixKeepsItsName(): void
    {
        $this->assertSame([3 => 'Onboarding'], SegmentSourceNames::apply([3 => 'Onboarding'], []));
    }

    public function testTheIdsAreKept(): void
    {
        $out = SegmentSourceNames::apply([10 => 'B', 2 => 'A'], [10 => 'X', 2 => 'X']);

        $this->assertSame([2, 10], array_keys($out));
    }

    public function testTheListIsSortedByTheNameShown(): void
    {
        $out = SegmentSourceNames::apply([1 => 'Alfa', 2 => 'Beta', 3 => 'Zeta'], [1 => 'Vendas', 3 => 'Compras']);

        $this->assertSame([2 => 'Beta', 3 => 'Compras - Zeta', 1 => 'Vendas - Alfa'], $out);
    }

    public function testAnEmptyPrefixIsIgnored(): void
    {
        $this->assertSame([1 => 'Alfa'], SegmentSourceNames::apply([1 => 'Alfa'], [1 => '']));
    }
}
