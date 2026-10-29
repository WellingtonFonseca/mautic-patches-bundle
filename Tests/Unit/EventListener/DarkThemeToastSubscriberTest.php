<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\DarkThemeToastSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DarkThemeToastSubscriberTest extends TestCase
{
    private function css(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new DarkThemeToastSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, DarkThemeToastSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeftOnly(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);
        $this->assertSame('', $this->css('page.header.right'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function themes(): array
    {
        return ['dark' => ['dark'], 'solarized dark' => ['solarized-dark']];
    }

    #[DataProvider('themes')]
    public function testTheToastFollowsThePageInTheDarkThemes(string $theme): void
    {
        $css = $this->css();

        $this->assertStringContainsString(':root[theme="'.$theme.'"] .alert-growl{background:color-mix(in srgb,var(--layer-02) 94%,transparent);color:var(--text-primary);', $css);
    }

    #[DataProvider('themes')]
    public function testOnlyThePlainLinksAndTheCloseButtonFollowTheText(string $theme): void
    {
        $css  = $this->css();
        $root = ':root[theme="'.$theme.'"] .alert-growl';

        $this->assertStringContainsString($root.':not(.alert-growl--warning):not(.alert-growl--error) a,', $css);
        $this->assertStringContainsString($root.' .close{color:var(--text-primary)}', $css);
        $this->assertStringNotContainsString('--warning a{', $css, 'the yellow link of a warning stays core\'s');
    }

    public function testTheLightThemesAreNotTouched(): void
    {
        $this->assertStringNotContainsString('theme="light"', $this->css());
        $this->assertStringNotContainsString('solarized-light', $this->css());
    }
}
