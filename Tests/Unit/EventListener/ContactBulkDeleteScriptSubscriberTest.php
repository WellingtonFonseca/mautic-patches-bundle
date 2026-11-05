<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactBulkDeleteScriptSubscriber;
use PHPUnit\Framework\TestCase;

class ContactBulkDeleteScriptSubscriberTest extends TestCase
{
    private function content(string $context): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new ContactBulkDeleteScriptSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame([CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent'], ContactBulkDeleteScriptSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $content = $this->content('page.header.left');

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringEndsWith('</script>', $content);
        $this->assertStringContainsString('Mautic.patchesContactBulkDeleteOnLoad', $content); // the file itself
        $this->assertStringContainsString('mauticPatchesContactBulkDeleteApi.install(mQuery,Mautic);', $content);
        $this->assertSame('', $this->content('something.else'));
    }
}
