<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\PluginSettingsTabsSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:plugin-settings')]
#[Group('risk:UI')]
#[Group('risk:FLUXO')]
class PluginSettingsTabsSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new PluginSettingsTabsSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, PluginSettingsTabsSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheStyleAndBothScriptsOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        $this->assertStringContainsString('.patches-changelog{max-height:60vh;overflow-y:auto}', $content);
        $this->assertStringContainsString('.patches-about__icon{height:160px;margin:0 auto 16px}', $content);
        $this->assertStringContainsString('.patches-about__title{margin:0 0 8px}', $content);
        $this->assertStringEndsWith(';mauticPatchesChangelogTabApi.install(mQuery,Mautic);</script>', $content);
        $this->assertLessThan(
            strpos($content, 'mauticPatchesChangelogTabApi.install'),
            strpos($content, 'mauticPatchesAboutTabApi.install(mQuery,Mautic);'),
            'the About script is installed first'
        );
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheStyleHoldsEveryRuleAndIsClosedBeforeTheScriptsOpen(): void
    {
        $content = $this->content();

        $this->assertMatchesRegularExpression(
            '#^<style>.*\.patches-changelog\{[^}]*\}.*\.patches-changelog__day ul\{margin:4px 0 0;padding-left:20px\}</style><script>#s',
            $content,
            'every rule is inside the style element, and the script opens right after it'
        );
        $this->assertStringEndsWith('</script>', $content);
        $this->assertSame(1, substr_count($content, '<script>'));
    }

    public function testOnlyTheModalThatHoldsTheAboutBlockIsWidenedTo780px(): void
    {
        $this->assertStringContainsString('.modal-dialog:has(.patches-about-block){width:780px;max-width:95vw}', $this->content());
    }

    public function testTheScriptsDefineTheCallbacksTheTemplatesName(): void
    {
        $views = __DIR__.'/../../../Resources/views/Integration/';

        foreach (['About' => '_about', 'Changelog' => '_changelog'] as $name => $template) {
            $this->assertStringContainsString('Mautic.patches'.$name.'Tab', $this->content());
            $this->assertStringContainsString('data-onload-callback="patches'.$name.'Tab"', file_get_contents($views.$template.'.html.twig'));
        }
    }
}
