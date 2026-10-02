<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\DarkThemeButtonSubscriber;
use PHPUnit\Framework\TestCase;

class DarkThemeButtonSubscriberTest extends TestCase
{
    private function css(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new DarkThemeButtonSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, DarkThemeButtonSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeft(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);
    }

    public function testNothingIsInjectedOnOtherContexts(): void
    {
        $this->assertSame('', $this->css('page.header.right'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function states(): array
    {
        return ['hover' => [':hover'], 'active' => [':active'], 'focus' => [':focus']];
    }

    /**
     * @dataProvider states
     */
    public function testTheLightButtonsGetDarkTextInTheDarkTheme(string $state): void
    {
        $css = $this->css();

        // Save & Close and Cancel sit after Apply, which core styles with its "tertiary" rule by position.
        foreach (['.btn-tertiary', '.btn-primary+.btn-primary:not(.dropdown-toggle)', '.btn-primary+.btn-primary+.btn-primary', '.btn-primary+.btn-primary+.btn-secondary'] as $button) {
            $this->assertStringContainsString(':root[theme="dark"] '.$button.':not([disabled]):not(.disabled)'.$state, $css, $button.$state);
        }

        $this->assertMatchesRegularExpression('/\{color:var\(--text-inverse\)\}/', $css);
    }

    public function testTheIconsInsideTheButtonFollow(): void
    {
        $this->assertStringContainsString(':root[theme="dark"] .btn-primary+.btn-primary:not(.dropdown-toggle):not([disabled]):not(.disabled):hover i', $this->css());
    }

    /**
     * @dataProvider otherThemes
     */
    public function testOnlyTheDarkThemeIsTouched(string $theme): void
    {
        // The other themes keep a readable pair (contrast checked against core's own variables).
        $this->assertStringNotContainsString('theme="'.$theme.'"', $this->css());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherThemes(): array
    {
        return ['light' => ['light'], 'solarized light' => ['solarized-light'], 'solarized dark' => ['solarized-dark'], 'freire' => ['freire']];
    }

    public function testADisabledButtonIsLeftAlone(): void
    {
        $css = $this->css();

        // every selector excludes disabled buttons, so none ends at the state right after the button
        $this->assertStringNotContainsString('.btn-tertiary:hover', $css);
        $this->assertStringNotContainsString('.btn-primary+.btn-primary:not(.dropdown-toggle):hover', $css);
        $this->assertStringContainsString(':not([disabled]):not(.disabled):hover', $css);
    }
}
