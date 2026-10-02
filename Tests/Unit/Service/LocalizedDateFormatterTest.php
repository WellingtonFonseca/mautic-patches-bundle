<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\LocalizedDateFormatter;
use PHPUnit\Framework\TestCase;

class LocalizedDateFormatterTest extends TestCase
{
    private function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-02 02:35:00', new \DateTimeZone('America/Sao_Paulo'));
    }

    public function testPortugueseMonthName(): void
    {
        $text = (new LocalizedDateFormatter())->format($this->date(), 'pt_BR');

        $this->assertStringContainsString('outubro', $text);
        $this->assertStringContainsString('2026', $text);
        $this->assertStringContainsString('02:35', $text);
        $this->assertStringNotContainsString('October', $text);
    }

    public function testEnglishMonthName(): void
    {
        $text = (new LocalizedDateFormatter())->format($this->date(), 'en_US');

        $this->assertStringContainsString('October', $text);
        $this->assertStringContainsString('2026', $text);
    }

    public function testKeepsTheTimezoneOfTheGivenDate(): void
    {
        // 02:35 in São Paulo is 05:35 UTC; the text must show the São Paulo clock
        $text = (new LocalizedDateFormatter())->format($this->date(), 'pt_BR');

        $this->assertStringNotContainsString('05:35', $text);
    }

    public function testShortLocaleCodeFromTheTranslator(): void
    {
        $this->assertStringContainsString('outubro', (new LocalizedDateFormatter())->format($this->date(), 'pt'));
    }
}
