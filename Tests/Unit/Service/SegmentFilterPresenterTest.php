<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\SegmentFilterPresenter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentFilterPresenterTest extends TestCase
{
    private const CHOICES = [
        'lead' => [
            'email' => ['label' => 'Email', 'operators' => ['é igual a' => '=', 'está vazio' => 'empty']],
            'estado' => ['label' => 'Estado', 'operators' => ['inclui' => 'in'], 'properties' => ['list' => ['sp' => 'São Paulo', 'rj' => 'Rio de Janeiro']]],
            'nivel' => ['label' => 'Nível', 'operators' => ['é igual a' => '='], 'properties' => ['list' => [['value' => '1', 'label' => 'Básico']]]],
        ],
        'custom_object' => [
            'cmf_13' => ['label' => 'disciplinas : fim', 'operators' => ['menor que' => 'lt']],
        ],
    ];

    private function presenter(): SegmentFilterPresenter
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key): string => 'T('.$key.')');

        return new SegmentFilterPresenter($translator);
    }

    public function testWritesFieldOperatorAndValue(): void
    {
        $rows = $this->presenter()->present([
            ['glue' => 'and', 'object' => 'lead', 'field' => 'email', 'operator' => '=', 'properties' => ['filter' => 'a@b.com']],
        ], self::CHOICES);

        $this->assertSame([['glue' => 'and', 'field' => 'Email', 'operator' => 'é igual a', 'value' => 'a@b.com']], $rows);
    }

    public function testNoValueForEmptyOperators(): void
    {
        $rows = $this->presenter()->present([
            ['glue' => 'and', 'object' => 'lead', 'field' => 'email', 'operator' => 'empty', 'properties' => ['filter' => 'ignored']],
        ], self::CHOICES);

        $this->assertSame('', $rows[0]['value']);
        $this->assertSame('está vazio', $rows[0]['operator']);
    }

    public function testListValuesAreWrittenAsTheirLabels(): void
    {
        $rows = $this->presenter()->present([
            ['glue' => 'and', 'object' => 'lead', 'field' => 'estado', 'operator' => 'in', 'properties' => ['filter' => ['sp', 'rj']]],
            ['glue' => 'or', 'object' => 'lead', 'field' => 'nivel', 'operator' => '=', 'properties' => ['filter' => '1']],
        ], self::CHOICES);

        $this->assertSame('São Paulo, Rio de Janeiro', $rows[0]['value']);
        $this->assertSame('Básico', $rows[1]['value']);
        $this->assertSame('or', $rows[1]['glue']);
    }

    public function testRelativeDatesAreTranslated(): void
    {
        $rows = $this->presenter()->present([
            ['glue' => 'and', 'object' => 'custom_object', 'field' => 'cmf_13', 'operator' => 'lt', 'properties' => ['filter' => 'today']],
        ], self::CHOICES);

        $this->assertSame('disciplinas : fim', $rows[0]['field']);
        $this->assertSame('menor que', $rows[0]['operator']);
        $this->assertSame('T(mautic.lead.list.today)', $rows[0]['value']);
    }

    public function testDisplayWinsOverTheStoredValue(): void
    {
        $rows = $this->presenter()->present([
            ['glue' => 'and', 'object' => 'lead', 'field' => 'email', 'operator' => '=', 'display' => 'Fulano', 'properties' => ['filter' => '10']],
        ], self::CHOICES);

        $this->assertSame('Fulano', $rows[0]['value']);
    }

    public function testUnknownFieldAndOperatorShowTheirAlias(): void
    {
        $rows = $this->presenter()->present([
            ['object' => 'lead', 'field' => 'gone', 'operator' => 'zzz', 'filter' => 'x'],
        ], self::CHOICES);

        $this->assertSame([['glue' => 'and', 'field' => 'gone', 'operator' => 'zzz', 'value' => 'x']], $rows);
    }

    public function testNoFiltersNoRows(): void
    {
        $this->assertSame([], $this->presenter()->present([], self::CHOICES));
    }
}
