<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentUpdateLockSubscriber;
use PHPUnit\Framework\TestCase;

class SegmentUpdateLockSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SegmentUpdateLockSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentUpdateLockSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testLocksOnlyTheClickedSegmentById(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('var locked={};', $js);
        $this->assertStringContainsString('if(locked[id]){event.preventDefault();event.stopImmediatePropagation();return;}', $js);
        $this->assertStringContainsString('locked[id]=true;', $js);
        $this->assertStringContainsString('if(!id||!locked[id]){return;}', $js);
    }

    public function testASecondClickNeverReachesCoresAjaxHandler(): void
    {
        $this->assertStringContainsString('},true);', $this->content(), 'capture phase');
    }

    public function testTheLockedButtonIsOnlyDisabledAndKeepsItsIcon(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('addClass("disabled")', $js);
        $this->assertStringNotContainsString('ri-loader', $js);
        $this->assertStringNotContainsString('ri-fw', $js);
        $this->assertStringNotContainsString('.dropdown-toggle', $js);
    }

    public function testTheUpdatedOnLineOfTheClickedSegmentBecomesUpdating(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery(".segment-last-built")', $js);
        $this->assertStringContainsString('if(!locked[id]||$line.data("mauticPatchesUpdating")){return;}', $js);
        $this->assertStringContainsString('$line.data("updating-text")', $js);
        $this->assertStringContainsString('<svg viewBox="0 0 24 24"', $js, 'an inline SVG, symmetric around its center');
        $this->assertStringContainsString('M3.05469 13H5.07065', $js, 'the loader-3-line shape');
        $this->assertStringContainsString('animation:ri-spin', $js, "core's own keyframes");
    }

    public function testTheUpdatingTextIsBoldInTheThemesLinkColorAndPulses(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new SegmentUpdateLockSubscriber())->injectViewCustomContent($event);
        $all = implode('', $event->getContent());

        $this->assertStringContainsString('<small class=\\"mautic-patches-updating\\">', $all);
        $this->assertStringContainsString('<span class=\\"mautic-patches-updating-text\\">', $all);
        $this->assertStringContainsString('.mautic-patches-updating{color:var(--link-primary);font-weight:600}', $all);
        $this->assertStringContainsString('@keyframes mauticPatchesPulse', $all);
        $this->assertStringContainsString('.mautic-patches-updating-text{display:inline-block;animation:mauticPatchesPulse 1.2s ease-in-out infinite}', $all);
    }

    public function testThePulseIsNotSwitchedOffByTheReducedMotionSetting(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        (new SegmentUpdateLockSubscriber())->injectViewCustomContent($event);

        $this->assertStringNotContainsString('prefers-reduced-motion', implode('', $event->getContent()));
    }

    public function testTheLockIsAppliedAgainAfterEveryAjaxPageLoad(): void
    {
        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);decorate();return result;}', $this->content());
    }

    public function testAsksTheServerWhetherTheRebuildIsDoneAndComparesWithTheDateSeenAtTheClick(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('var statusUrl=(a.getAttribute("href")||"").split("?")[0]+"/status";', $js);
        $this->assertStringContainsString('mQuery.ajax({url:statusUrl,dataType:"json",global:false})', $js);
        $this->assertStringContainsString('if(baseline===undefined){baseline=r.lastBuilt;next();return;}', $js);
        $this->assertStringContainsString('if(r.lastBuilt!==baseline){finish();return;}', $js);
    }

    public function testPollsEveryThreeSecondsAndGivesUpAfterTwoMinutes(): void
    {
        $js = $this->content();

        $this->assertSame(3000, SegmentUpdateLockSubscriber::POLL_MS);
        $this->assertSame(120000, SegmentUpdateLockSubscriber::MAX_WAIT_MS);
        $this->assertStringContainsString('setTimeout(check,3000)', $js);
        $this->assertStringContainsString('Date.now()-started>=120000', $js);
    }

    public function testWhenDoneOrGivenUpItUnlocksAndReloadsOnlyOnTheSamePage(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('delete locked[id];', $js);
        $this->assertStringContainsString('if(window.location.pathname+window.location.search===where){Mautic.loadContent(where);}', $js);
        $this->assertStringContainsString('.fail(next)', $js, 'a failed check is retried, not a dead end');
    }

    public function testNothingIsStoredInTheBrowser(): void
    {
        $js = $this->content();

        foreach (['localStorage', 'sessionStorage', 'cookie', 'indexedDB'] as $storage) {
            $this->assertStringNotContainsString($storage, $js);
        }
    }

    public function testInstallsOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesSegmentUpdateLock){return;}', $this->content());
    }
}
