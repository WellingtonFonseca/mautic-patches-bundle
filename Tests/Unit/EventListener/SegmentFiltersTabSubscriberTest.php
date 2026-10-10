<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFiltersTabSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentFiltersTabSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/s/segment-filters/0');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Filtros');

        $event = new CustomContentEvent('some_view', $context);
        (new SegmentFiltersTabSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentFiltersTabSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheTabIsAddedFirstAndIsTheActiveOne(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('$nav.prepend(', $js);
        $this->assertStringContainsString('$contacts.parent().prepend($pane)', $js);
        $this->assertStringContainsString('"Filtros"', $js);
        $this->assertStringContainsString('$nav.children("li").removeClass("active")', $js);
        $this->assertStringContainsString('$contacts.siblings(".tab-pane").addBack().removeClass("active")', $js);
        $this->assertStringContainsString('"class":"tab-pane bdr-w-0 active"', $js);
        $this->assertStringContainsString('mQuery("<li>",{"class":"active"})', $js);
    }

    public function testTheSegmentIdComesFromTheContactsPaneAndTheFiltersAreLoadedFromTheRoute(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('data-target-url', $js);
        $this->assertStringContainsString('mQuery.get("/s/segment-filters/"+m[1])', $js);
    }

    public function testActsOnlyOnTheSegmentPageAndOnlyOnce(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('if(!$contacts.length||mQuery("#segment-filters-container").length){return;}', $js);
        $this->assertStringContainsString('if(window.mauticPatchesSegmentFiltersTab){return;}', $js);
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);addTab();return result;}', $js);
        $this->assertStringContainsString('mQuery(addTab);', $js);
    }
}
