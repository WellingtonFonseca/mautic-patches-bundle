<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignNodeScheduleSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignNodeScheduleSubscriberTest extends TestCase
{
    private function subscriber(): CampaignNodeScheduleSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => '<'.$key.'>&');

        return new CampaignNodeScheduleSubscriber($translator);
    }

    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        $this->subscriber()->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheScriptCarriesTheFormatterAndConfiguresItWithTheWords(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringEndsWith('</script>', $content);
        $this->assertStringContainsString('root.mauticPatchesScheduleApi', $content);
        $this->assertStringContainsString(';window.mauticPatchesScheduleApi.configure({"hour":', $content);
        $this->assertStringContainsString('"day_6":', $content);
    }

    public function testEveryWordTheFormatterReadsIsGiven(): void
    {
        $js    = (string) file_get_contents(__DIR__.'/../../../Resources/js/campaign-node-schedule.js');
        $words = $this->subscriber()->words();

        preg_match_all('/words\.(\w+)/', $js, $named);
        foreach (array_unique($named[1]) as $name) {
            $this->assertArrayHasKey($name, $words, "the formatter reads words.$name");
        }
        $this->assertStringContainsString("words['day_'", $js, 'the days are read by index');
        for ($day = 0; $day <= 6; ++$day) {
            $this->assertArrayHasKey('day_'.$day, $words);
        }
    }

    public function testTheWordsCannotCloseTheScriptTagOrLeakAnAmpersand(): void
    {
        $content = $this->content();

        $slash = chr(92); // JSON_HEX_TAG and JSON_HEX_AMP write < > & as a backslash and u003C, u003E, u0026
        $this->assertStringNotContainsString('<mautic.patches', $content);
        $this->assertStringContainsString($slash.'u003Cmautic.patches.campaign.schedule.hour'.$slash.'u003E'.$slash.'u0026', $content);
    }

    public function testTheWordsAreTheTranslationsOfTheSchedulePrefix(): void
    {
        $words = $this->subscriber()->words();

        $this->assertSame('<mautic.patches.campaign.schedule.at>&', $words['at']);
        $this->assertSame('<mautic.patches.campaign.schedule.day_6>&', $words['day_6']);
        $this->assertCount(12, $words);
    }

    public function testOnlyThePageHeaderGetsTheScript(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }

    public function testItListensToTheCustomContentOfTheViews(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignNodeScheduleSubscriber::getSubscribedEvents());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['pt_BR' => ['pt_BR'], 'en_US' => ['en_US']];
    }

    #[DataProvider('locales')]
    public function testEveryWordIsTranslatedInBothLanguages(string $locale): void
    {
        $ini = parse_ini_file(__DIR__.'/../../../Translations/'.$locale.'/messages.ini') ?: [];

        foreach (array_keys($this->subscriber()->words()) as $name) {
            $this->assertNotEmpty($ini['mautic.patches.campaign.schedule.'.$name] ?? '', "$locale: $name");
        }
    }

    #[DataProvider('locales')]
    public function testThePlaceholdersAreTheOnesTheFormatterFills(string $locale): void
    {
        $ini = parse_ini_file(__DIR__.'/../../../Translations/'.$locale.'/messages.ini') ?: [];
        $key = static fn (string $name): string => (string) $ini['mautic.patches.campaign.schedule.'.$name];

        $this->assertStringContainsString('%hour%', $key('hour'));
        $this->assertStringContainsString('%hour%', $key('hour_minutes'));
        $this->assertStringContainsString('%minutes%', $key('hour_minutes'));
        $this->assertStringContainsString('%time%', $key('at'));
        $this->assertStringContainsString('%start%', $key('window'));
        $this->assertStringContainsString('%end%', $key('window'));
        $this->assertStringContainsString('%from%', $key('range'));
        $this->assertStringContainsString('%to%', $key('range'));
    }
}
