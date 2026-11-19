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

        $this->formatter = new RelativeWaitNameFormatter($this->translator);
    }

    #[DataProvider('pluralDataProvider')]
    public function testFormatWithPluralRules(int $amount, string $unit, string $locale, string $expected): void
    {
        $this->translator->setLocale($locale);
        $result = $this->formatter->format($amount, $unit);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function pluralDataProvider(): array
    {
        return [
            // Portuguese Brazil - days
            [1, 'd', 'pt_BR', 'Esperar 1 dia'],
            [0, 'd', 'pt_BR', 'Esperar 0 dias'],
            [2, 'd', 'pt_BR', 'Esperar 2 dias'],
            [5, 'd', 'pt_BR', 'Esperar 5 dias'],

            // Portuguese Brazil - weeks
            [1, 'w', 'pt_BR', 'Esperar 1 semana'],
            [2, 'w', 'pt_BR', 'Esperar 2 semanas'],

            // Portuguese Brazil - months
            [1, 'm', 'pt_BR', 'Esperar 1 mês'],
            [2, 'm', 'pt_BR', 'Esperar 2 meses'],

            // Portuguese Brazil - years
            [1, 'y', 'pt_BR', 'Esperar 1 ano'],
            [2, 'y', 'pt_BR', 'Esperar 2 anos'],

            // Portuguese Brazil - hours
            [1, 'h', 'pt_BR', 'Esperar 1 hora'],
            [2, 'h', 'pt_BR', 'Esperar 2 horas'],

            // Portuguese Brazil - minutes
            [1, 'i', 'pt_BR', 'Esperar 1 minuto'],
            [2, 'i', 'pt_BR', 'Esperar 2 minutos'],

            // English - days
            [1, 'd', 'en_US', 'Wait 1 day'],
            [0, 'd', 'en_US', 'Wait 0 days'],
            [2, 'd', 'en_US', 'Wait 2 days'],

            // English - weeks
            [1, 'w', 'en_US', 'Wait 1 week'],
            [2, 'w', 'en_US', 'Wait 2 weeks'],

            // English - months
            [1, 'm', 'en_US', 'Wait 1 month'],
            [2, 'm', 'en_US', 'Wait 2 months'],

            // English - years
            [1, 'y', 'en_US', 'Wait 1 year'],
            [2, 'y', 'en_US', 'Wait 2 years'],

            // English - hours
            [1, 'h', 'en_US', 'Wait 1 hour'],
            [2, 'h', 'en_US', 'Wait 2 hours'],

            // English - minutes
            [1, 'i', 'en_US', 'Wait 1 minute'],
            [2, 'i', 'en_US', 'Wait 2 minutes'],
        ];
    }

    public function testUnknownUnitUsesUnknownKey(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->format(5, 'x');

        $this->assertSame('Esperar 5', $result);
    }

    public function testUnknownUnitEnglish(): void
    {
        $this->translator->setLocale('en_US');
        $result = $this->formatter->format(5, 'invalid');

        $this->assertSame('Wait 5', $result);
    }

    public function testNegativeAmountBecomesZero(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->format(-5, 'd');

        $this->assertSame('Esperar 0 dias', $result);
    }

    public function testFromPropertiesWithDefaults(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(null);

        $this->assertSame('Esperar 1 dia', $result);
    }

    public function testFromPropertiesWithEmptyArray(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties([]);

        $this->assertSame('Esperar 1 dia', $result);
    }

    public function testFromPropertiesWithCustomValues(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(['amount' => 7, 'unit' => 'w']);

        $this->assertSame('Esperar 7 semanas', $result);
    }

    public function testFromPropertiesWithStringAmount(): void
    {
        $this->translator->setLocale('pt_BR');
        $result = $this->formatter->fromProperties(['amount' => '3', 'unit' => 'h']);

        $this->assertSame('Esperar 3 horas', $result);
    }
}
