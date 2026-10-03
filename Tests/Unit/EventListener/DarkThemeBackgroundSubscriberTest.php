<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\DarkThemeBackgroundSubscriber;
use PHPUnit\Framework\TestCase;

class DarkThemeBackgroundSubscriberTest extends TestCase
{
    private function css(): string
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new DarkThemeBackgroundSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, DarkThemeBackgroundSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeftOnly(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);

        $other = new CustomContentEvent('some_view', 'something.else');
        (new DarkThemeBackgroundSubscriber())->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function darkThemes(): array
    {
        return ['dark' => ['dark'], 'solarized dark' => ['solarized-dark']];
    }

    /**
     * @dataProvider darkThemes
     */
    public function testWhiteUtilityClassesFollowTheThemeAndKeepImportant(string $theme): void
    {
        $css = $this->css();

        foreach (['.bg-white', '.bg-auto'] as $class) {
            $this->assertStringContainsString(
                ':root[theme="'.$theme.'"] '.$class.'{background-color:var(--background)!important;border-color:var(--border-subtle)!important;color:var(--text-primary)!important}',
                $css
            );
        }
    }

    /**
     * @dataProvider darkThemes
     */
    public function testTheModalRuleOfTheCoreIsBeatenToo(string $theme): void
    {
        $this->assertStringContainsString(':root[theme="'.$theme.'"] .modal .box-layout .bg-auto{', $this->css());
    }

    public function testNeverUsesAFixedColor(): void
    {
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $this->css());
    }

    public function testLightThemesAreNotTouched(): void
    {
        $css = $this->css();

        $this->assertStringNotContainsString('theme="light"', $css);
        $this->assertStringNotContainsString('solarized-light', $css);
    }
}
