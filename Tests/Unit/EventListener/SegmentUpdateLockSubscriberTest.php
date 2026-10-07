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
        $this->assertStringEndsWith('</script>', $this->content());
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

    public function testLockedLinkIsDisabledWithASpinnerOnItAndOnTheRowToggle(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('addClass("disabled")', $js);
        $this->assertStringContainsString('ri-loader-3-line ri-spin', $js);
        $this->assertStringContainsString('.siblings(".dropdown-toggle")', $js);
    }

    public function testTheLockIsAppliedAgainAfterEveryAjaxPageLoad(): void
    {
        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);decorate();return result;}', $this->content());
    }

    public function testUnlocksAndReloadsAfterTheTimeOnlyOnTheSamePage(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('},'.SegmentUpdateLockSubscriber::LOCK_MS.');', $js);
        $this->assertStringContainsString('delete locked[id];', $js);
        $this->assertStringContainsString('if(window.location.pathname+window.location.search===where){Mautic.loadContent(where);}', $js);
    }

    public function testTenSeconds(): void
    {
        $this->assertSame(10000, SegmentUpdateLockSubscriber::LOCK_MS);
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
