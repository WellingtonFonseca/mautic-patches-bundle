<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFilterSelectorSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentFilterSelectorSubscriberTest extends TestCase
{
    private const PANE = ':root #filters:has(> .form-group > .available-filters)';

    private function css(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentFilterSelectorSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentFilterSelectorSubscriber::getSubscribedEvents());
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

    public function testTheFiltersComeBeforeTheSelector(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(self::PANE.' > .alert{order:0}', $css, 'the notice stays on top');
        $this->assertStringContainsString(self::PANE.' > #leadlist_filters{order:1}', $css, 'the filters already added');
        $this->assertStringContainsString(self::PANE.' > .form-group{order:2}', $css, 'the selector that adds one, last');
    }

    public function testOnlyTheOpenPaneBecomesAColumn(): void
    {
        // a tab pane is display:none until .active: the flex layout must not show the closed ones
        $css = $this->css();

        $this->assertStringContainsString(self::PANE.'.active{display:flex;flex-direction:column}', $css);
        $this->assertSame(1, substr_count($css, 'display:flex'));
    }

    public function testEveryRuleIsScopedToThePaneThatHoldsTheSelector(): void
    {
        $css = $this->css();

        foreach (array_filter(explode('}', substr($css, strlen('<style>'), -strlen('</style>')))) as $rule) {
            $this->assertStringStartsWith(self::PANE, $rule);
        }
    }
}
