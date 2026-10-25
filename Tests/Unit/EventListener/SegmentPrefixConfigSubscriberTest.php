<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\CoreBundle\Helper\BundleHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixConfigSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SegmentPrefixConfigSubscriberTest extends TestCase
{
    public function testRunsAfterCoreListener(): void
    {
        $this->assertLessThan(0, SegmentPrefixConfigSubscriber::getSubscribedEvents()[ConfigEvents::CONFIG_ON_GENERATE][1]);
    }

    public function testAddsTheParameterToTheSegmentForm(): void
    {
        $event = new ConfigBuilderEvent($this->createMock(BundleHelper::class));
        $event->addForm(['formAlias' => 'segment_config', 'bundle' => 'LeadBundle', 'parameters' => ['segment_rebuild_time_warning' => 30]]);

        (new SegmentPrefixConfigSubscriber())->onConfigGenerate($event);

        $this->assertSame(
            ['segment_rebuild_time_warning' => 30, 'segment_prefixes' => []],
            $event->getForms()['segment_config']['parameters']
        );
        $this->assertSame('LeadBundle', $event->getForms()['segment_config']['bundle']);
    }

    public function testDoesNothingWithoutTheSegmentForm(): void
    {
        $event = new ConfigBuilderEvent($this->createMock(BundleHelper::class));

        (new SegmentPrefixConfigSubscriber())->onConfigGenerate($event);

        $this->assertSame([], $event->getForms());
    }
}
