<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Translations;

use PHPUnit\Framework\TestCase;

class SegmentRebuildTranslationsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function startedMessages(): array
    {
        return [
            'pt_BR' => ['pt_BR', 'mautic.patches.segment.rebuild.started="O segmento está sendo atualizado"'],
            'en_US' => ['en_US', 'mautic.patches.segment.rebuild.started="The segment is being updated"'],
        ];
    }

    /**
     * @dataProvider startedMessages
     */
    public function testStartedMessageIsShortAndTranslated(string $locale, string $line): void
    {
        $file = file_get_contents(__DIR__.'/../../../Translations/'.$locale.'/flashes.ini');

        $this->assertStringContainsString($line."\n", (string) $file);
    }
}
