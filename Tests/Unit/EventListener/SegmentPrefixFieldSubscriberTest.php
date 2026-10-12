<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixFieldSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentPrefixFieldSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentPrefixFieldSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentPrefixFieldSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testScriptMovesTheRealFieldBeforeTheName(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#leadlist_prefix")', $js);
        $this->assertStringContainsString('insertBefore($name.closest(".form-group"))', $js);
        $this->assertStringContainsString('Mautic.onPageLoad=function()', $js);
    }
}
