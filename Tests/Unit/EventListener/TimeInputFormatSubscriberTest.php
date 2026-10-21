<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\TimeInputFormatSubscriber;
use PHPUnit\Framework\TestCase;

class TimeInputFormatSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new TimeInputFormatSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, TimeInputFormatSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheFormatterOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();
        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesTimeInputFormatApi.install(mQuery,document)', $content);
        $this->assertSame('', $this->content('page.header.right'));
    }
}
