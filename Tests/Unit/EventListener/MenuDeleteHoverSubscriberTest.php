<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\MenuDeleteHoverSubscriber;
use PHPUnit\Framework\TestCase;

class MenuDeleteHoverSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new MenuDeleteHoverSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, MenuDeleteHoverSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<style>', $this->content());
        $this->assertStringEndsWith('</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheTextOfTheDeleteItemIsBlackOnHoverAndFocusInTheDarkThemesOnly(): void
    {
        $css = MenuDeleteHoverSubscriber::style();

        foreach (['dark', 'solarized-dark'] as $theme) {
            // the class of Mautic 5 and the id core 7 uses
            foreach (['a.danger', 'a#delete'] as $item) {
                $root = ':root[theme="'.$theme.'"] .dropdown-menu>li '.$item;
                $this->assertStringContainsString($root.':hover,'.$root.':focus{color:#000}', $css);
                foreach (['hover', 'focus'] as $state) {
                    $this->assertStringContainsString($root.':'.$state.' i', $css);
                    $this->assertStringContainsString($root.':'.$state.' span', $css);
                }
            }
        }
        // every rule is under a dark theme: the light themes keep core's white
        foreach (array_filter(explode('}', $css)) as $rule) {
            $this->assertMatchesRegularExpression('/^:root\[theme="(dark|solarized-dark)"\]/', $rule);
        }
        $this->assertStringNotContainsString(':root[theme="light"]', $css);
        $this->assertStringNotContainsString('background', $css);
    }
}
