<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentLastBuiltSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\LocalizedDateFormatter;
use PHPUnit\Framework\TestCase;

class SegmentLastBuiltSubscriberTest extends TestCase
{
    private function subscriber(string $locale = 'pt_BR'): SegmentLastBuiltSubscriber
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('getLocale')->willReturn($locale);

        return new SegmentLastBuiltSubscriber($translator, new LocalizedDateFormatter());
    }

    public function testSubscribesToCustomContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentLastBuiltSubscriber::getSubscribedEvents());
    }

    public function testAddsTheLastBuiltTemplateUnderTheSegmentName(): void
    {
        $segment = new LeadList();
        $event   = new CustomContentEvent('some_view', 'segment.name', ['item' => $segment]);

        $this->subscriber()->injectViewCustomContent($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('@MauticPatches/Segment/last_built.html.twig', $templates[0]['template']);
        $this->assertSame($segment, $templates[0]['vars']['item']);
        $this->assertNull($templates[0]['vars']['builtText']);
    }

    public function testGivesTheDateWrittenInTheUsersLanguage(): void
    {
        $segment = new LeadList();
        $segment->setLastBuiltDate(new \DateTime('2026-10-02 05:35:00', new \DateTimeZone('UTC')));
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => $segment]);

        $this->subscriber('pt_BR')->injectViewCustomContent($event);

        $this->assertStringContainsString('outubro', $event->getTemplates()[0]['vars']['builtText']);
    }

    public function testNothingForOtherContexts(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left', ['item' => new LeadList()]);

        $this->subscriber()->injectViewCustomContent($event);

        $this->assertSame([], $event->getTemplates());
    }

    public function testNothingWhenTheItemIsNotASegment(): void
    {
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => ['id' => 1]]);

        $this->subscriber()->injectViewCustomContent($event);

        $this->assertSame([], $event->getTemplates());
    }

    public function testTemplateShowsTheTextOrNeverBuilt(): void
    {
        $template = (string) file_get_contents(__DIR__.'/../../../Resources/views/Segment/last_built.html.twig');

        $this->assertStringContainsString('builtText is not null', $template);
        $this->assertStringContainsString('builtText', $template);
        $this->assertStringNotContainsString('dateToFull', $template);
        $this->assertStringContainsString('mautic.patches.segment.last_built', $template);
        $this->assertStringContainsString('mautic.patches.segment.never_built', $template);
    }
}
