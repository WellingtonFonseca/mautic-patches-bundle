<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\PublishToggleLoadingSubscriber;
use PHPUnit\Framework\TestCase;

class PublishToggleLoadingSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new PublishToggleLoadingSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, PublishToggleLoadingSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<style>', $this->content());
        $this->assertStringEndsWith('</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheLoaderGlyphReplacesTheToggleOneWhileTheIconSpins(): void
    {
        $css = PublishToggleLoadingSubscriber::STYLE;

        // the icon that has the spin and the loader AND a toggle class gets the loader's glyph (the code of ri-loader-3-line)
        $this->assertStringContainsString('.ri-spin.ri-loader-3-line.ri-toggle-fill:before', $css);
        $this->assertStringContainsString('.ri-spin.ri-loader-3-line.ri-toggle-line:before', $css);
        $this->assertStringContainsString('content:"\eec4"', $css);
        // neutral color instead of the green / red of the state
        $this->assertStringContainsString('color:var(--text-secondary)!important', $css);
    }
}
