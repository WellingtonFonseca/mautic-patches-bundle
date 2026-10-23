<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactViewSidePanelSubscriber;
use PHPUnit\Framework\TestCase;

class ContactViewSidePanelSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnCallback(fn (string $key): string => 'T:'.$key);

        $event = new CustomContentEvent('some_view', $context);
        (new ContactViewSidePanelSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ContactViewSidePanelSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testFindsTheDetailPagesByTheirRightColumnOutsideForms(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#app-content .col-md-3.bdr-l")', $js);
        $this->assertStringContainsString('$right.siblings(".col-md-9")', $js);
        $this->assertStringContainsString('$right.closest("form").length', $js);
        $this->assertStringContainsString('$left.data("mauticPatchesSidePanel")', $js);
    }

    public function testAlwaysStartsHidden(): void
    {
        $this->assertStringContainsString('apply(true);', $this->content());
    }

    public function testNeverRemembersTheChoice(): void
    {
        $js = $this->content();

        foreach (['localStorage', 'sessionStorage', 'cookie', 'indexedDB'] as $storage) {
            $this->assertStringNotContainsString($storage, $js);
        }
    }

    public function testTheMiddleColumnTakesTheWholeWidthWhileHidden(): void
    {
        $this->assertStringContainsString('$left.css("width",hidden?"100%":"")', $this->content());
    }

    public function testTheButtonIsCoresPrimaryTextButtonWithMoreAndAnArrow(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('class=\"btn btn-primary btn-nospin\"', $js);
        $this->assertStringContainsString('ri-arrow-right-s-line', $js);
        $this->assertStringContainsString('"T:mautic.patches.contact.side_panel.more"', $js);
        $this->assertStringNotContainsString('btn-icon', $js);
        $this->assertStringNotContainsString('btn-ghost', $js);
    }

    public function testTheButtonTogglesAndItsLabelFollowsTheState(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('$button.on("click",function(){apply(!$button.data("hidden"));});', $js);
        $this->assertStringContainsString('"T:mautic.patches.contact.side_panel.show"', $js);
        $this->assertStringContainsString('"T:mautic.patches.contact.side_panel.hide"', $js);
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);init();return result;}', $js);
        $this->assertStringContainsString('mQuery(init);', $js);
    }

    public function testInstallsOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesContactSidePanel){return;}', $this->content());
    }
}
