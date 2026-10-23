<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitIconSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use PHPUnit\Framework\TestCase;

class RelativeWaitIconSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new RelativeWaitIconSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheWaitNodeHasThePauseIconOfRemixIcon(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        // the node of the builder and of the Preview has data-event="<type>"; core's default icon is `ri-shapes-fill`
        $this->assertStringContainsString('[data-event="'.RelativeWaitSubscriber::TYPE.'"] .campaign-event-icon i.ri-shapes-fill:before', $content);
        // Remix Icon's ri-pause-fill
        $this->assertStringContainsString('content:"\efd7"', $content);
    }

    public function testNoOtherNodeIsTouched(): void
    {
        $this->assertSame(1, substr_count($this->content(), '[data-event='), 'one rule, for the Wait node only');
    }

    public function testOnlyThePageHeaderGetsTheStyle(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }

    public function testItListensToTheCustomContentOfTheViews(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, RelativeWaitIconSubscriber::getSubscribedEvents());
    }
}
