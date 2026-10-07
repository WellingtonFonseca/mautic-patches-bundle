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

    public function testFindsTheContactPageByItsDetailsBlockAndTheColumnNextToIt(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#lead-details").closest(".col-md-9")', $js);
        $this->assertStringContainsString('$left.siblings(".col-md-3")', $js);
        $this->assertStringContainsString('if(!$left.length||$left.data("mauticPatchesSidePanel")){return;}', $js);
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

    public function testTheButtonIsCoresRoundIconButton(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('btn btn-primary btn-icon btn-nospin', $js);
        $this->assertStringContainsString('data-toggle=\"tooltip\"', $js);
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
