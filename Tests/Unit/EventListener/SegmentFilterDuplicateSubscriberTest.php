<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFilterDuplicateSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:segment')]
#[Group('risk:FLUXO')]
#[Group('risk:CRITICO')]
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

        $this->assertStringStartsWith('<style>', $js);
        $this->assertStringContainsString('</style><script>', $js);
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

    public function testCoreButtonsAreHiddenAndTheBadgesPushTheValueInput(): void
    {
        $css = SegmentFilterDuplicateSubscriber::STYLE;

        // core's three buttons are gone; the badges are flex siblings of the value form (they push it, never overlay it)
        $this->assertStringContainsString('.segment-filter .segment-button{display:none}', $css);
        $this->assertStringContainsString('.patches-filter-row{display:flex', $css);
        $this->assertStringContainsString('.patches-filter-row>.properties-form{flex:1 1 auto;min-width:0}', $css);
        $this->assertStringContainsString('.patches-filter-actions{flex:none', $css);
        $this->assertStringNotContainsString('.patches-filter-badge{position:absolute', $css);
    }

    public function testTheBadgeStyleOfTheListsIsShippedWithTheScript(): void
    {
        $this->assertStringContainsString('.patches-badge', $this->content('page.header.left'));
    }

    public function testTheScriptWaitsForTheValueFormBeforeAdicionar(): void
    {
        $js = $this->content('page.header.left');

        $this->assertStringContainsString('Mautic.loadFilterForm', $js);
        $this->assertStringContainsString('draftReloads', $js);
        $this->assertStringContainsString('patches-filter-dock__add', $js);
    }
}
