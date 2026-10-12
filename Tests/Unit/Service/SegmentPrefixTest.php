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
            ['PUCPR_POS | Onboarding'],
            ['ESPM_POS | Onboarding'],
            ['IES1 | Nome com | barra'],
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
            ['PUCPR_POS |Onboarding'],
            ['PUCPR_POS| Onboarding'],
            [' | Onboarding'],
            ['PUCPR_POS | '],
            ['PUCPR POS | Onboarding'],
            ['Copy of PUCPR_POS | Onboarding'],
        ];
    }

    public function testSplit(): void
    {
        $this->assertSame(['prefix' => 'PUCPR_POS', 'name' => 'Onboarding'], SegmentPrefix::split('PUCPR_POS | Onboarding'));
        $this->assertSame(['prefix' => 'A', 'name' => 'B | C'], SegmentPrefix::split('A | B | C'));
    }

    public function testSplitOfANameWithoutPrefixKeepsTheNameWhole(): void
    {
        $this->assertSame(['prefix' => '', 'name' => 'Onboarding'], SegmentPrefix::split('Onboarding'));
    }

    public function testJoinTrimsAndRoundTrips(): void
    {
        $this->assertSame('PUCPR_POS | Onboarding', SegmentPrefix::join(' PUCPR_POS ', ' Onboarding '));
        $this->assertSame(['prefix' => 'X', 'name' => 'Y'], SegmentPrefix::split(SegmentPrefix::join('X', 'Y')));
    }
}
