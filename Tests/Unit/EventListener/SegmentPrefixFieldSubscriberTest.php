<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixFieldSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentPrefixFieldSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key) => 'T:'.$key);

        $event = new CustomContentEvent('some_view', $context);
        (new SegmentPrefixFieldSubscriber($translator))->injectViewCustomContent($event);

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

    public function testScriptUsesTheTranslatedTexts(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('"T:mautic.patches.segment.prefix.placeholder"', $js);
        $this->assertStringContainsString('"T:mautic.patches.segment.prefix"', $js);
    }

    public function testScriptKeepsTheRealNameInputAndWrapsPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#leadlist_name")', $js);
        $this->assertStringContainsString('$full.hide().after($row)', $js);
        $this->assertStringContainsString('Mautic.onPageLoad=function()', $js);
    }
}
