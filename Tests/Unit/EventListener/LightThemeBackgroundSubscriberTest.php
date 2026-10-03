<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\LightThemeBackgroundSubscriber;
use PHPUnit\Framework\TestCase;

class LightThemeBackgroundSubscriberTest extends TestCase
{
    private function css(): string
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new LightThemeBackgroundSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, LightThemeBackgroundSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<style', $this->css());

        $other = new CustomContentEvent('some_view', 'something.else');
        (new LightThemeBackgroundSubscriber())->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testThePageBackgroundBecomesLightGrayInTheLightTheme(): void
    {
        $this->assertMatchesRegularExpression('/:root\[theme="light"\]\{[^}]*--background:#f2f2f2;/', $this->css());
    }

    public function testNothingIsPureWhiteAnymore(): void
    {
        preg_match('/:root\[theme="light"\]\{([^}]*)\}/', $this->css(), $m);

        $this->assertDoesNotMatchRegularExpression('/#f{3}(f{3})?\b/i', $m[1] ?? '');
    }

    public function testTheWhiteUtilityClassesFollowTheBackground(): void
    {
        $css = $this->css();

        foreach (['.bg-white', '.bg-auto', '.modal .box-layout .bg-auto'] as $selector) {
            $this->assertStringContainsString(':root[theme="light"] '.$selector.'{background-color:var(--background)!important;', $css);
        }
    }

    public function testOnlyTheLightThemeIsTouched(): void
    {
        $css = $this->css();

        foreach (['solarized-light', 'solarized-dark', 'freire', '"dark"'] as $other) {
            $this->assertStringNotContainsString($other, $css);
        }
    }
}
