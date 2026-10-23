<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\PluginTileStatusSubscriber;
use PHPUnit\Framework\TestCase;

class PluginTileStatusSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new PluginTileStatusSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, PluginTileStatusSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringEndsWith('</script>', $content);
        $this->assertStringContainsString('mauticPatchesPluginTileStatusApi.install(mQuery,Mautic);', $content);
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptWrapsCoresIntegrationConfigOnLoad(): void
    {
        $this->assertStringContainsString('Mautic.integrationConfigOnLoad', $this->content());
    }
}
