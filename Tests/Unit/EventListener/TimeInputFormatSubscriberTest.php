<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Segment\RelativeDate;
use Symfony\Contracts\Translation\TranslatorInterface;
use MauticPlugin\MauticPatchesBundle\EventListener\TimeInputFormatSubscriber;
use PHPUnit\Framework\TestCase;

class TimeInputFormatSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left', ?array $english = null, ?array $local = null): string
    {
        $relativeDate = $this->createMock(RelativeDate::class);
        $relativeDate->method('getRelativeDateStrings')->willReturn($local ?? ['mautic.lead.list.today' => 'hoje', 'mautic.lead.list.yesterday' => 'ontém', 'mautic.lead.list.week_this' => 'esta semana']);

        // the same keys in English, as the translator gives them for en_US
        $english    = $english ?? ['mautic.lead.list.today' => 'today', 'mautic.lead.list.yesterday' => 'yesterday', 'mautic.lead.list.week_this' => 'this week'];
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key, array $parameters = [], ?string $domain = null, ?string $locale = null) => 'en_US' === $locale ? ($english[$key] ?? $key) : $key);

        $event = new CustomContentEvent('some_view', $context);
        (new TimeInputFormatSubscriber($relativeDate, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, TimeInputFormatSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheFormatterOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();
        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesTimeInputFormatApi.install(mQuery,document)', $content);
        $this->assertSame('', $this->content('page.header.right'));
    }

    public function testThePageGivesTheMapOfTheWordsItAcceptsToTheWordCoreKeepsRelative(): void
    {
        $content = $this->content();

        // the words of the user's language first, then English (the official documentation is in English), lower case, each to the word of the user's language
        $this->assertStringContainsString(
            'window.mauticPatchesRelativeDates={"hoje":"hoje","ont\u00e9m":"ont\u00e9m","esta semana":"esta semana","today":"hoje","yesterday":"ont\u00e9m","this week":"esta semana"};',
            $content
        );
        $this->assertLessThan(strpos($content, 'mauticPatchesTimeInputFormatApi.install'), strpos($content, 'mauticPatchesRelativeDates'), 'the map is there before the formatter starts');
    }

    public function testAMissingEnglishTranslationIsNotAnAcceptedWord(): void
    {
        // the translator gives the key back when the string is missing: that key must not become a word the user can type
        $content = $this->content('page.header.left', ['mautic.lead.list.today' => 'today']);

        $this->assertStringContainsString('"today":"hoje"', $content);
        $this->assertStringNotContainsString('mautic.lead.list', $content);
    }

    public function testTheWordOfTheLanguageOfTheUserWinsOverAnEnglishWordThatIsTheSame(): void
    {
        // the pt_BR word of one keyword equal to the English word of another: the word of the user's language keeps its own keyword
        $content = $this->content('page.header.left', ['mautic.lead.list.today' => 'tomorrow', 'mautic.lead.list.tomorrow' => 'today'], ['mautic.lead.list.today' => 'today', 'mautic.lead.list.tomorrow' => 'tomorrow']);

        $this->assertStringContainsString('{"today":"today","tomorrow":"tomorrow"}', $content);
    }
}
