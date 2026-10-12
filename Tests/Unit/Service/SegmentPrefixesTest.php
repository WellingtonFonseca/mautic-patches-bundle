<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use PHPUnit\Framework\TestCase;

class SegmentPrefixesTest extends TestCase
{
    private function service(mixed $value): SegmentPrefixes
    {
        $helper = $this->createMock(CoreParametersHelper::class);
        $helper->method('get')->with('segment_prefixes', [])->willReturn($value);

        return new SegmentPrefixes($helper);
    }

    public function testAllTrimsAndDropsEmptyAndRepeatedLines(): void
    {
        $this->assertSame(['Compras', 'Vendas'], $this->service([' Compras ', '', 'Vendas', 'Compras'])->all());
    }

    public function testNotAListMeansNoPrefix(): void
    {
        $this->assertSame([], $this->service(null)->all());
        $this->assertSame([], $this->service('Compra')->all());
    }

    public function testHasIsExactAndCaseSensitive(): void
    {
        $service = $this->service(['Compras']);

        $this->assertTrue($service->has('Compras'));
        $this->assertFalse($service->has('Compra'));
        $this->assertFalse($service->has('compras'));
    }

    public function testProblemOfTheConfigurationLines(): void
    {
        $this->assertNull(SegmentPrefixes::problem(['Compras', '', ' Vendas ']));
        $this->assertSame('invalid', SegmentPrefixes::problem(['Nova Compra']));
        $this->assertSame('invalid', SegmentPrefixes::problem(['Compras|Vendas']));
        $this->assertSame('duplicate', SegmentPrefixes::problem(['Compras', 'Compras ']));
    }
}
