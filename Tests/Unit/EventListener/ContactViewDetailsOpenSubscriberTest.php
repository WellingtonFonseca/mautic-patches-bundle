<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactViewDetailsOpenSubscriber;
use PHPUnit\Framework\TestCase;

class ContactViewDetailsOpenSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new ContactViewDetailsOpenSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ContactViewDetailsOpenSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testOpensTheDetailsBlockAndFixesTheCaret(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("#lead-details")', $js);
        $this->assertStringContainsString('$block.addClass("in")', $js);
        $this->assertStringContainsString('removeClass("collapsed")', $js);
    }

    public function testDoesNothingOnAPageWithoutTheBlock(): void
    {
        $this->assertStringContainsString('if(!$block.length||$block.data("mauticPatchesOpened")){return;}', $this->content());
    }

    public function testOpensEachBlockOnlyOnce(): void
    {
        $this->assertStringContainsString('$block.data("mauticPatchesOpened",true)', $this->content());
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);open();return result;}', $js);
        $this->assertStringContainsString('mQuery(open);', $js);
    }

    public function testInstallsOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesContactDetailsOpen){return;}', $this->content());
    }
}
