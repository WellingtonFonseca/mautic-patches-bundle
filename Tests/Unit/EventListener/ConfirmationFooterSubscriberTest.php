<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ConfirmationFooterSubscriber;
use PHPUnit\Framework\TestCase;

class ConfirmationFooterSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new ConfirmationFooterSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ConfirmationFooterSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        $this->assertStringContainsString('.confirmation-modal .modal-content{padding:0;overflow:hidden;border-radius:var(--border-radius-lg)', $content);
        $this->assertStringContainsString('<script>', $content);
        $this->assertStringEndsWith('</script>', $content);
        $this->assertStringContainsString('mauticPatchesConfirmationFooterApi.install(mQuery,Mautic);', $content);
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptWrapsCoresShowConfirmation(): void
    {
        $this->assertStringContainsString('Mautic.showConfirmation', $this->content());
    }

    public function testTheDeleteButtonIsWhiteInLightThemesAndBlackInDarkOnes(): void
    {
        $css = ConfirmationFooterSubscriber::deleteColors();

        $this->assertStringContainsString('.btn-danger,:root .confirmation-modal .modal-footer .btn-danger i{color:#fff}', $css);
        foreach (['dark', 'solarized-dark'] as $theme) {
            $root = ':root[theme="'.$theme.'"] .confirmation-modal .modal-footer .btn-danger';
            $this->assertStringContainsString($root.','.$root.' i{color:#000}', $css);
        }
        $this->assertStringContainsString($css, $this->content());
    }
}
