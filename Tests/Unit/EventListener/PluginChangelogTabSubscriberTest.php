<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\PluginChangelogTabSubscriber;
use PHPUnit\Framework\TestCase;

class PluginChangelogTabSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new PluginChangelogTabSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, PluginChangelogTabSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheStyleAndTheScriptOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        $this->assertStringContainsString('.patches-changelog{max-height:60vh;overflow-y:auto}', $content);
        $this->assertStringContainsString('<script>', $content);
        $this->assertStringEndsWith('mauticPatchesChangelogTabApi.install(mQuery,Mautic);</script>', $content);
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptDefinesTheCallbackTheTemplateNames(): void
    {
        $this->assertStringContainsString('Mautic.patchesChangelogTab', $this->content());
        $this->assertStringContainsString('data-onload-callback="patchesChangelogTab"', file_get_contents(__DIR__.'/../../../Resources/views/Integration/_changelog.html.twig'));
    }
}
