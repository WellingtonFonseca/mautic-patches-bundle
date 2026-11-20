<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RelativeWait;

use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\IniFileLoader;
use Symfony\Component\Translation\Translator;

#[Group('area:campaign-wait')]
#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class RelativeWaitNameFormatterTest extends TestCase
{
    private RelativeWaitNameFormatter $formatter;

    private Translator $translator;

    protected function setUp(): void
    {
        $this->translator = new Translator('pt_BR');
        $this->translator->addLoader('ini', new IniFileLoader());
        $this->translator->addResource('ini', __DIR__.'/../../../../Translations/pt_BR/messages.ini', 'pt_BR');
        $this->translator->addResource('ini', __DIR__.'/../../../../Translations/en_US/messages.ini', 'en_US');

        $catalogMock = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $this->formatter = new RelativeWaitNameFormatter($this->translator, $catalogMock);
    }

    #[DataProvider('pluralDataProvider')]
    public function testFormatWithPluralRules(int $amount, string $unit, string $locale, string $expected): void
    {
        $this->translator->setLocale($locale);
        $result = $this->formatter->format($amount, $unit, 'invalid', null);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function pluralDataProvider(): array
    {
        return [
            // Portuguese Brazil - days
            [1, 'd', 'pt_BR', "1 dia (campo de data não encontrado)"],
            [0, 'd', 'pt_BR', "0 dias (campo de data não encontrado)"],
            [2, 'd', 'pt_BR', "2 dias (campo de data não encontrado)"],
            [5, 'd', 'pt_BR', "5 dias (campo de data não encontrado)"],

            // Portuguese Brazil - weeks
            [1, 'w', 'pt_BR', "1 semana (campo de data não encontrado)"],
            [2, 'w', 'pt_BR', "2 semanas (campo de data não encontrado)"],

            // Portuguese Brazil - months
            [1, 'm', 'pt_BR', "1 mês (campo de data não encontrado)"],
            [2, 'm', 'pt_BR', "2 meses (campo de data não encontrado)"],

            // Portuguese Brazil - years
            [1, 'y', 'pt_BR', "1 ano (campo de data não encontrado)"],
            [2, 'y', 'pt_BR', "2 anos (campo de data não encontrado)"],

            // Portuguese Brazil - hours
            [1, 'h', 'pt_BR', "1 hora (campo de data não encontrado)"],
            [2, 'h', 'pt_BR', "2 horas (campo de data não encontrado)"],

            // Portuguese Brazil - minutes
            [1, 'i', 'pt_BR', "1 minuto (campo de data não encontrado)"],
            [2, 'i', 'pt_BR', "2 minutos (campo de data não encontrado)"],

            // English - days
            [1, 'd', 'en_US', "1 day (date field not found)"],
            [0, 'd', 'en_US', "0 days (date field not found)"],
            [2, 'd', 'en_US', "2 days (date field not found)"],

            // English - weeks
            [1, 'w', 'en_US', "1 week (date field not found)"],
            [2, 'w', 'en_US', "2 weeks (date field not found)"],

            // English - months
            [1, 'm', 'en_US', "1 month (date field not found)"],
            [2, 'm', 'en_US', "2 months (date field not found)"],

            // English - years
            [1, 'y', 'en_US', "1 year (date field not found)"],
            [2, 'y', 'en_US', "2 years (date field not found)"],

            // English - hours
            [1, 'h', 'en_US', "1 hour (date field not found)"],
            [2, 'h', 'en_US', "2 hours (date field not found)"],

            // English - minutes
            [1, 'i', 'en_US', "1 minute (date field not found)"],
            [2, 'i', 'en_US', "2 minutes (date field not found)"],
        ];
    }

    public function testUnknownUnitUsesUnknownKey(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->format(5, 'x', 'invalid', null);

        $this->assertSame("5 (campo de data não encontrado)", $result);
    }

    public function testUnknownUnitEnglish(): void
    {
        $this->translator->setLocale('en_US');
        $result = $this->formatter->format(5, 'invalid', 'invalid', null);

        $this->assertSame("5 (date field not found)", $result);
    }

    public function testNegativeAmountBecomesZero(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->format(-5, 'd', 'invalid', null);

        $this->assertSame("0 dias (campo de data não encontrado)", $result);
    }

    public function testFromPropertiesWithDefaults(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(null);

        $this->assertSame("1 dia (campo de data não encontrado)", $result);
    }

    public function testFromPropertiesWithEmptyArray(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties([]);

        $this->assertSame("1 dia (campo de data não encontrado)", $result);
    }

    public function testFromPropertiesWithCustomValues(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(['amount' => 7, 'unit' => 'w']);

        $this->assertSame("7 semanas (campo de data não encontrado)", $result);
    }

    public function testFromPropertiesWithStringAmount(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(['amount' => '3', 'unit' => 'h']);

        $this->assertSame("3 horas (campo de data não encontrado)", $result);
    }

    public function testFormatWithDirectionAfterProducesFourLines(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $catalog->method('labelOf')->willReturn('Contato: Data de Atribuição');

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->format(2, 'd', 'after', 'Contato: Data de Atribuição');

        $this->assertSame('2 dias depois de Contato: Data de Atribuição', $result);
    }

    public function testFormatWithDirectionBeforeProducesFourLines(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $catalog->method('labelOf')->willReturn('Contato: Data de Atribuição');

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->format(5, 'w', 'before', 'Contato: Data de Atribuição');

        $this->assertSame('5 semanas antes de Contato: Data de Atribuição', $result);
    }

    public function testFormatWithInvalidDirectionProducesThreeLines(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $catalog->method('labelOf')->willReturn('Contato: Data');

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->format(1, 'd', 'invalid', 'Contato: Data');

        $this->assertSame('1 dia Contato: Data', $result);
    }

    public function testFormatWithNullLabelUsesMissingPlaceholder(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $catalog->method('labelOf')->willReturn(null);

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->format(1, 'd', 'after', null);

        $this->assertStringNotContainsString("\n", $result);
        $this->assertStringContainsString('campo', strtolower($result));
    }

    public function testFormatTruncatesAtOneHundredNinetyOneCharacters(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $longLabel = str_repeat('x', 200);
        $catalog->method('labelOf')->willReturn($longLabel);

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->format(1, 'd', 'after', $longLabel);

        $this->assertLessThanOrEqual(191, mb_strlen($result), 'Format output should be truncated to 191 characters');
    }

    public function testFromPropertiesReadsDirection(): void
    {
        $this->translator->setLocale('pt_BR');
        $catalog = $this->createMock(\MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog::class);
        $catalog->method('labelOf')->with('contact:attribution_date')->willReturn('Attribution Date');

        $formatter = new RelativeWaitNameFormatter($this->translator, $catalog);
        $result = $formatter->fromProperties(['amount' => 3, 'unit' => 'd', 'direction' => 'before', 'dateField' => 'contact:attribution_date']);

        $this->assertSame('3 dias antes de Attribution Date', $result);
    }
}
