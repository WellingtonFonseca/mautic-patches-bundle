<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFilterUiSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFilterDuplicateSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:segment')]
#[Group('risk:FLUXO')]
#[Group('risk:CRITICO')]
class SegmentFilterDuplicateSubscriberTest extends TestCase
{
    private function switch(bool $uiOn): CoreParametersHelper
    {
        $helper = $this->createMock(CoreParametersHelper::class);
        $helper->method('get')->with(SegmentFilterUiSubscriber::PARAMETER)->willReturn($uiOn);

        return $helper;
    }

    private function content(string $context, bool $uiOn = false): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentFilterDuplicateSubscriber($this->switch($uiOn)))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentFilterDuplicateSubscriber::getSubscribedEvents());
    }

    public function testInjectsOnlyTheScriptOnPageHeaderLeft(): void
    {
        $js = $this->content('page.header.left');

        // the old screen is core's own form: no <style>, no badges, no dock
        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringNotContainsString('<style>', $js);
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

    public function testTheOldScreenKeepsNoRedesignMockup(): void
    {
        $this->assertFalse(defined(SegmentFilterDuplicateSubscriber::class.'::STYLE'));
        $this->assertFalse(defined(SegmentFilterDuplicateSubscriber::class.'::DOCK_STYLE'));

        $js = $this->content('page.header.left');
        foreach (['patches-badge', 'patches-filter-dock', 'patches-filter-row', 'patches-filter-actions', 'draftReloads', 'Mautic.loadFilterForm'] as $gone) {
            $this->assertStringNotContainsString($gone, $js);
        }
    }

    public function testTheDockStyleBelongsToTheNewScreen(): void
    {
        $this->assertStringContainsString('.patches-filter-dock{', SegmentFilterUiSubscriber::DOCK_STYLE);
        $this->assertStringContainsString('.patches-filter-dock__add', SegmentFilterUiSubscriber::DOCK_STYLE);
    }

    public function testTheOldScreenIsNotInjectedWhenTheNewFilterScreenIsOn(): void
    {
        $this->assertSame('', $this->content('page.header.left', true));
    }
}
