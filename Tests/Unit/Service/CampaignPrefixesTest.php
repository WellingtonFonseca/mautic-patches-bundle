<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixes;
use PHPUnit\Framework\TestCase;

class CampaignPrefixesTest extends TestCase
{
    private function service(mixed $value): CampaignPrefixes
    {
        $helper = $this->createMock(CoreParametersHelper::class);
        $helper->method('get')->with('campaign_prefixes', [])->willReturn($value);

        return new CampaignPrefixes($helper);
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
        $this->assertNull(CampaignPrefixes::problem(['Compras', '', ' Vendas ']));
        $this->assertSame('invalid', CampaignPrefixes::problem(['Nova Compra']));
        $this->assertSame('invalid', CampaignPrefixes::problem(['Compras|Vendas']));
        $this->assertSame('duplicate', CampaignPrefixes::problem(['Compras', 'Compras ']));
    }
}
