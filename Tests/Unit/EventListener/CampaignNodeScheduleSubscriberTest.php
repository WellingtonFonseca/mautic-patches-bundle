<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignNodeScheduleSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class CampaignNodeScheduleSubscriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $system the system's settings (locale, default_timezone)
     */
    private function subscriber(?User $user = null, array $system = ['locale' => 'pt_BR', 'default_timezone' => 'UTC']): CampaignNodeScheduleSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => '<'.$key.'>&');
        $userHelper = $this->createMock(UserHelper::class);
        // a guest must give null, not a new empty user: the argument is part of what is tested
        $userHelper->method('getUser')->with(true)->willReturn($user);
        $parameters = $this->createMock(CoreParametersHelper::class);
        $parameters->method('get')->willReturnCallback(static fn (string $name) => $system[$name] ?? null);

        return new CampaignNodeScheduleSubscriber($translator, $userHelper, $parameters);
    }

    private function user(?string $locale, ?string $timezone): User
    {
        $user = new User();
        $user->setLocale($locale);
        $user->setTimezone($timezone);

        return $user;
    }

    /** @return array<int, string> */
    private function contentOf(CampaignNodeScheduleSubscriber $subscriber, string $context = 'page.header.left'): array
    {
        $event = new CustomContentEvent('some_view', $context);
        $subscriber->injectViewCustomContent($event);

        return $event->getContent();
    }

    private function content(string $context = 'page.header.left'): string
    {
        return implode('', $this->contentOf($this->subscriber(), $context));
    }

    public function testTheScriptCarriesTheFormatterAndConfiguresItWithTheWords(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringEndsWith('</script>', $content);
        $this->assertStringContainsString('root.mauticPatchesScheduleApi', $content);
        $this->assertStringContainsString(';window.mauticPatchesScheduleApi.configure({"hour":', $content);
        $this->assertStringContainsString('"day_6":', $content);
        $this->assertStringContainsString('"date_plain":', $content);
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
        $this->assertCount(14, $words);
        $this->assertSame('<mautic.campaign.connection.trigger.date.label>&', $words['date_plain']);
        $this->assertSame('<mautic.campaign.connection.trigger.date.label_inaction>&', $words['date_inaction']);
    }

    public function testTheZoneIsTheUsersOwnSettingWhenThereIsOne(): void
    {
        $zone = $this->subscriber($this->user('en_US', 'America/Sao_Paulo'))->zone();

        $this->assertSame(['locale' => 'en-US', 'timeZone' => 'America/Sao_Paulo'], $zone);
    }

    public function testTheZoneFallsBackToTheSystemSettingsTheWayCoreDoesAtLogin(): void
    {
        $system = ['locale' => 'pt_BR', 'default_timezone' => 'America/Sao_Paulo'];

        $this->assertSame(['locale' => 'pt-BR', 'timeZone' => 'America/Sao_Paulo'], $this->subscriber($this->user(null, null), $system)->zone());
        $this->assertSame(['locale' => 'pt-BR', 'timeZone' => 'America/Sao_Paulo'], $this->subscriber($this->user('', ''), $system)->zone());
        $this->assertSame(['locale' => 'pt-BR', 'timeZone' => 'America/Sao_Paulo'], $this->subscriber(null, $system)->zone());
    }

    public function testTheUserCanSetOnlyOneOfThem(): void
    {
        $system = ['locale' => 'pt_BR', 'default_timezone' => 'America/Sao_Paulo'];

        $this->assertSame(['locale' => 'en-US', 'timeZone' => 'America/Sao_Paulo'], $this->subscriber($this->user('en_US', null), $system)->zone());
        $this->assertSame(['locale' => 'pt-BR', 'timeZone' => 'Europe/Lisbon'], $this->subscriber($this->user(null, 'Europe/Lisbon'), $system)->zone());
    }

    public function testAZoneOrLocaleThatIsMissingOrNotKnownBecomesUtcAndEnglish(): void
    {
        $this->assertSame(['locale' => 'en-US', 'timeZone' => 'UTC'], $this->subscriber(null, [])->zone());
        $this->assertSame(['locale' => 'en-US', 'timeZone' => 'UTC'], $this->subscriber(null, ['locale' => '', 'default_timezone' => ''])->zone());
        $this->assertSame('UTC', $this->subscriber($this->user(null, 'Nowhere/Land'), [])->zone()['timeZone']);
        $this->assertSame('UTC', $this->subscriber(null, ['default_timezone' => 'brt'])->zone()['timeZone']);
    }

    public function testTheScriptConfiguresTheFormatterWithTheZoneToo(): void
    {
        $content = implode('', $this->contentOf($this->subscriber($this->user('pt_BR', 'America/Sao_Paulo'))));

        $this->assertStringContainsString('},{"locale":"pt-BR","timeZone":"America\/Sao_Paulo"});</script>', $content);
    }

    public function testTheLocaleCannotCloseTheScriptTagEither(): void
    {
        $slash   = chr(92);
        $content = implode('', $this->contentOf($this->subscriber($this->user('x</script>&y', 'UTC'))));

        $this->assertStringNotContainsString('</script>&', $content);
        $this->assertStringContainsString('"locale":"x'.$slash.'u003C'.$slash.'/script'.$slash.'u003E'.$slash.'u0026y"', $content);
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

        // the date templates are core's own keys, translated by core's language pack
        $own = array_filter(array_keys($this->subscriber()->words()), static fn (string $name): bool => !str_starts_with($name, 'date_'));
        $this->assertCount(12, $own);
        foreach ($own as $name) {
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
