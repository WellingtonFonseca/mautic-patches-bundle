<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixColumnSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentPrefixColumnSubscriberTest extends TestCase
{
    private function subscriber(?string $prefix = 'Compras'): SegmentPrefixColumnSubscriber
    {
        $store = $this->createMock(SegmentPrefixStore::class);
        $store->method('get')->willReturn($prefix);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Prefixo');

        return new SegmentPrefixColumnSubscriber($store, $translator);
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentPrefixColumnSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeft(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);

        $js = implode('', $event->getContent());
        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"Prefixo"', $js);
        $this->assertStringContainsString('$name.before($cell)', $js);
        $this->assertStringContainsString('$head.before(', $js);
    }

    public function testEachRowGetsAMarkerTemplateWithItsPrefix(): void
    {
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => new LeadList()]);
        $this->subscriber('Vendas')->injectViewCustomContent($event);

        $this->assertSame(
            [['template' => '@MauticPatches/Segment/prefix_marker.html.twig', 'vars' => ['prefix' => 'Vendas']]],
            $event->getTemplates()
        );
    }

    public function testOtherContextsAndNonSegmentsAreIgnored(): void
    {
        $event = new CustomContentEvent('some_view', 'something.else', ['item' => new LeadList()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
        $this->assertSame([], $event->getContent());

        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => new \stdClass()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
    }
}
