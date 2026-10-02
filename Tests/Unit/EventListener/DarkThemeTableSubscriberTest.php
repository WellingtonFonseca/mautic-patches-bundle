<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\DarkThemeTableSubscriber;
use PHPUnit\Framework\TestCase;

class DarkThemeTableSubscriberTest extends TestCase
{
    private function css(): string
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new DarkThemeTableSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, DarkThemeTableSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeft(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);
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
    public function testStripedOddRowsFollowTheThemeInDarkThemes(string $theme): void
    {
        $this->assertStringContainsString(
            ':root[theme="'.$theme.'"] .table-striped>tbody>tr:nth-of-type(odd){background-color:var(--layer-01)}',
            $this->css()
        );
    }

    /**
     * @dataProvider darkThemes
     */
    public function testHoverOfStripedRowsFollowsTheThemeAndComesAfterTheOddRule(string $theme): void
    {
        $css  = $this->css();
        $hover = ':root[theme="'.$theme.'"] .table-striped>tbody>tr:nth-of-type(odd):hover';

        $this->assertStringContainsString($hover, $css);
        $this->assertGreaterThan(
            strpos($css, ':root[theme="'.$theme.'"] .table-striped>tbody>tr:nth-of-type(odd){'),
            strpos($css, $hover)
        );
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

    public function testDoesNothingOnOtherContexts(): void
    {
        $event = new CustomContentEvent('some_view', 'some.other.context');
        (new DarkThemeTableSubscriber())->injectViewCustomContent($event);

        $this->assertSame([], $event->getContent());
    }
}
