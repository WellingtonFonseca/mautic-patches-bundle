<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentLastBuiltSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentLastBuiltSubscriberTest extends TestCase
{
    public function testSubscribesToCustomContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentLastBuiltSubscriber::getSubscribedEvents());
    }

    public function testAddsTheLastBuiltTemplateUnderTheSegmentName(): void
    {
        $segment = new LeadList();
        $event   = new CustomContentEvent('some_view', 'segment.name', ['item' => $segment]);

        (new SegmentLastBuiltSubscriber())->injectViewCustomContent($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('@MauticPatches/Segment/last_built.html.twig', $templates[0]['template']);
        $this->assertSame(['item' => $segment], $templates[0]['vars']);
    }

    public function testNothingForOtherContexts(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left', ['item' => new LeadList()]);

        (new SegmentLastBuiltSubscriber())->injectViewCustomContent($event);

        $this->assertSame([], $event->getTemplates());
    }

    public function testNothingWhenTheItemIsNotASegment(): void
    {
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => ['id' => 1]]);

        (new SegmentLastBuiltSubscriber())->injectViewCustomContent($event);

        $this->assertSame([], $event->getTemplates());
    }

    public function testTemplateTranslatesAndFormatsTheDateWithTheUsersTimezoneAndShowsNeverBuilt(): void
    {
        $template = (string) file_get_contents(__DIR__.'/../../../Resources/views/Segment/last_built.html.twig');

        $this->assertStringContainsString('item.lastBuiltDate', $template);
        $this->assertStringContainsString('dateToFull(', $template);
        $this->assertStringContainsString('mautic.patches.segment.last_built', $template);
        $this->assertStringContainsString('mautic.patches.segment.never_built', $template);
    }
}
