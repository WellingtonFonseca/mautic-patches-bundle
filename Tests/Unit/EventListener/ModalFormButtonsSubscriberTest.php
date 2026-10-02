<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ModalFormButtonsSubscriber;
use PHPUnit\Framework\TestCase;

class ModalFormButtonsSubscriberTest extends TestCase
{
    private function css(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new ModalFormButtonsSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ModalFormButtonsSubscriber::getSubscribedEvents());
    }

    public function testInjectsAStyleBlockOnPageHeaderLeft(): void
    {
        $css = $this->css();

        $this->assertStringStartsWith('<style', $css);
        $this->assertStringEndsWith('</style>', $css);
    }

    public function testNothingIsInjectedOnOtherContexts(): void
    {
        $this->assertSame('', $this->css('page.header.right'));
    }

    public function testTheButtonsShareTheWholeWidthOfTheFooterEqually(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(':root .modal-footer .modal-form-buttons .btn{', $css);
        $this->assertStringContainsString('flex:1 1 0', $css);
        $this->assertStringContainsString('width:auto', $css, 'core gives each button a fixed width');
    }

    public function testTheFooterItselfIsNotRestyled(): void
    {
        // the alignment, gap and the rounded corners of the first/last button stay core's
        $css = $this->css();

        $this->assertStringNotContainsString('justify-content', $css);
        $this->assertStringNotContainsString('border-radius', $css);
        $this->assertStringNotContainsString('gap', $css);
    }
}
