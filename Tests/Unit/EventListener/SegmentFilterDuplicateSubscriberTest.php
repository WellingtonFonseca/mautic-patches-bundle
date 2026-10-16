<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFilterDuplicateSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentFilterDuplicateSubscriberTest extends TestCase
{
    private function content(string $context): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentFilterDuplicateSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentFilterDuplicateSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $js = $this->content('page.header.left');

        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringEndsWith('mauticPatchesFilterDuplicateApi.install(mQuery,Mautic);</script>', $js);
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptCopiesThroughCoreFunctionsAndTheChipsScan(): void
    {
        $js = $this->content('page.header.left');

        $this->assertStringContainsString('Mautic.reorderSegmentFilters()', $js);
        $this->assertStringContainsString('Mautic.triggerOnPropertiesFormLoadedEvent', $js);
        $this->assertStringContainsString('Mautic.segmentFilter().attachEvents', $js);
        $this->assertStringContainsString('mauticPatchesInChipsScan', $js);
        $this->assertStringContainsString('Duplicar condição', $js);
    }
}
