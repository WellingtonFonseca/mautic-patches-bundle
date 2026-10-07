<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;

/**
 * The "Updated on ..." line carries what SegmentUpdateLockSubscriber's script
 * reads: the segment id (to find the line of the segment whose Update is
 * locked) and the text that replaces it. The script and the template meet only
 * through these names, so a rename on one side must fail here.
 */
class LastBuiltTemplateTest extends TestCase
{
    private string $template;

    protected function setUp(): void
    {
        $this->template = (string) file_get_contents(__DIR__.'/../../../Resources/views/Segment/last_built.html.twig');
    }

    public function testTheLineHasTheClassTheScriptLooksFor(): void
    {
        $this->assertStringContainsString('class="text-muted mt-4 segment-last-built"', $this->template);
    }

    public function testTheLineCarriesTheSegmentIdAndTheUpdatingText(): void
    {
        $this->assertStringContainsString('data-segment-id="{{ item.id }}"', $this->template);
        $this->assertStringContainsString("data-updating-text=\"{{ 'mautic.patches.segment.updating'|trans }}\"", $this->template);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function languages(): array
    {
        return [['en_US'], ['pt_BR']];
    }

    /**
     * @dataProvider languages
     */
    public function testTheUpdatingTextExistsInEveryLanguage(string $locale): void
    {
        $ini = (string) file_get_contents(__DIR__.'/../../../Translations/'.$locale.'/messages.ini');

        $this->assertMatchesRegularExpression('/^mautic\.patches\.segment\.updating=".+"$/m', $ini);
    }

    public function testSideSlotKeysAreInEveryLanguage(): void
    {
        foreach (['en_US', 'pt_BR'] as $locale) {
            $ini = (string) file_get_contents(__DIR__.'/../../../Translations/'.$locale.'/messages.ini');

            foreach (['mautic.patches.contact.side_panel.show', 'mautic.patches.contact.side_panel.hide', 'mautic.patches.segment.last_built', 'mautic.patches.segment.never_built'] as $key) {
                $this->assertStringContainsString($key.'=', $ini, $locale);
            }
        }
    }
}
