<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentAlias;
use PHPUnit\Framework\TestCase;

class SegmentAliasTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testGenerate(string $prefix, string $name, string $expected): void
    {
        $this->assertSame($expected, SegmentAlias::generate($prefix, $name));
    }

    public static function cases(): array
    {
        return [
            'the example'            => ['PUCPR', 'nome - do meu segmento', 'pucprnomedomeusegmento'],
            'dashes and underscores' => ['Compras', 'Meu---segmento_novo', 'comprasmeusegmentonovo'],
            'accents'                => ['Vendas', 'Ação Rápida', 'vendasacaorapida'],
            'numbers are kept'       => ['Compras', 'Turma 2026', 'comprasturma2026'],
            'symbols'                => ['Compras', 'A & B (teste)!', 'comprasabteste'],
            'only symbols'           => ['', '!! - ??', ''],
        ];
    }
}
