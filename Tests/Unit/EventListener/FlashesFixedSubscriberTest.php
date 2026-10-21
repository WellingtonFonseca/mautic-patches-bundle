<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\FlashesFixedSubscriber;
use PHPUnit\Framework\TestCase;

class FlashesFixedSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new FlashesFixedSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, FlashesFixedSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<style>', $this->content());
        $this->assertStringEndsWith('</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheContainerOfTheNotificationsIsFixedToTheScreenAndNothingElseChanges(): void
    {
        $this->assertSame(':root .alert-growl-container{position:fixed}', FlashesFixedSubscriber::STYLE);
    }
}
