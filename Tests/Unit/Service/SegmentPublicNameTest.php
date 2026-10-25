<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentPublicName;
use PHPUnit\Framework\TestCase;

class SegmentPublicNameTest extends TestCase
{
    public function testThePublicNameIsThePrefixAndTheNameWithoutTheirEdgeSpaces(): void
    {
        $this->assertSame('VENDAS - Leads quentes', SegmentPublicName::generate('  VENDAS ', "  Leads quentes\n"));
    }
}
