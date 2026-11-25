<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentFormSettingsTabSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:segment')]
#[Group('risk:FLUXO')]
class SegmentFormSettingsTabSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left', string $title = 'Configurações'): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('mautic.patches.segment.settings_tab')->willReturn($title);

        $event = new CustomContentEvent('some_view', $context);
        (new SegmentFormSettingsTabSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentFormSettingsTabSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheTabTitleIsTranslatedAndEscaped(): void
    {
        $this->assertStringContainsString('.text("Configurações")', $this->content());
        $this->assertStringContainsString('.text("a\\"b")', $this->content('page.header.left', 'a"b'));
    }

    public function testTheTabIsAddedFirstAndIsActiveByDefault(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('$nav.prepend(', $js);
        $this->assertStringContainsString('$details.parent().prepend($pane)', $js);
        $this->assertStringContainsString('$pane.addClass("active")', $js);
        $this->assertStringContainsString('$nav.children("li").removeClass("active");$details.removeClass("active in")', $js);
    }

    public function testAnotherPaneWithAnErrorKeepsItsDefaultUnlessSettingsHasTheError(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('var hasError=$pane.find(".has-error").length>0;', $js);
        $this->assertStringContainsString('var active=hasError||!$layout.find("#details,#filters").find(".has-error").length;', $js);
        $this->assertStringContainsString('$a.addClass("text-danger")', $js);
    }

    public function testTheFieldRowsMoveInsideTheSameFormAndTheSideColumnGoesAway(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('mQuery("form .box-layout")', $js);
        $this->assertStringContainsString('$layout.children(".col-md-3.bdr-l")', $js);
        $this->assertStringContainsString('$side.find(".pr-lg").children()', $js);
        $this->assertStringContainsString('$pane.append($kids);', $js);
        $this->assertStringContainsString('$side.remove();', $js);
        $this->assertStringContainsString('removeClass("col-md-9").addClass("col-md-12")', $js);
        $this->assertStringContainsString('id:"segment-settings-container"', $js);
    }

    public function testActsOnlyOnTheSegmentFormAndOnlyOnce(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('if(!$side.length||!$details.length||!$layout.find("#filters").length||!$nav.length||$layout.find("#segment-settings-container").length){return;}', $js);
        $this->assertStringContainsString('if(window.mauticPatchesSegmentFormSettingsTab){return;}', $js);
    }

    public function testRunsOnFirstLoadAndAfterEveryAjaxPageLoad(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('Mautic.onPageLoad=function(){var result=original.apply(this,arguments);moveFields();return result;}', $js);
        $this->assertStringContainsString('mQuery(moveFields);', $js);
    }

    public function testBothLanguagesHaveTheTabTitle(): void
    {
        foreach (['en_US', 'pt_BR'] as $locale) {
            $ini = file_get_contents(__DIR__.'/../../../Translations/'.$locale.'/messages.ini');
            $this->assertMatchesRegularExpression('/^mautic\.patches\.segment\.settings_tab="[^"]+"/m', $ini, $locale);
        }
    }
}
