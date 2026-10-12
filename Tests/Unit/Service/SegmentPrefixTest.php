<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefix;
use PHPUnit\Framework\TestCase;

class SegmentPrefixTest extends TestCase
{
    /**
     * @dataProvider validNames
     */
    public function testValidNames(string $name): void
    {
        $this->assertTrue(SegmentPrefix::isValid($name));
    }

    public static function validNames(): array
    {
        return [
            ['Compras | Onboarding'],
            ['Vendas | Onboarding'],
            ['Setor1 | Nome com | barra'],
            ['ÁGORA | Matrícula'],
        ];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testInvalidNames(?string $name): void
    {
        $this->assertFalse(SegmentPrefix::isValid($name));
    }

    public static function invalidNames(): array
    {
        return [
            [null],
            [''],
            ['Onboarding'],
            ['Compras |Onboarding'],
            ['Compras| Onboarding'],
            [' | Onboarding'],
            ['Compras | '],
            ['Nova Compra | Onboarding'],
            ['Copy of Compras | Onboarding'],
        ];
    }

    public function testSplit(): void
    {
        $this->assertSame(['prefix' => 'Compras', 'name' => 'Onboarding'], SegmentPrefix::split('Compras | Onboarding'));
        $this->assertSame(['prefix' => 'A', 'name' => 'B | C'], SegmentPrefix::split('A | B | C'));
    }

    public function testSplitOfANameWithoutPrefixKeepsTheNameWhole(): void
    {
        $this->assertSame(['prefix' => '', 'name' => 'Onboarding'], SegmentPrefix::split('Onboarding'));
    }

    public function testJoinTrimsAndRoundTrips(): void
    {
        $this->assertSame('Compras | Onboarding', SegmentPrefix::join(' Compras ', ' Onboarding '));
        $this->assertSame(['prefix' => 'X', 'name' => 'Y'], SegmentPrefix::split(SegmentPrefix::join('X', 'Y')));
    }
}
