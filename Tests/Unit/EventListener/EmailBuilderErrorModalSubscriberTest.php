<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\EmailBuilderErrorModalSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailBuilderErrorModalSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'mautic.core.close' === $id ? 'Fechar' : $id);

        $event = new CustomContentEvent('some_view', $context);
        (new EmailBuilderErrorModalSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, EmailBuilderErrorModalSubscriber::getSubscribedEvents());
    }

    public function testNothingIsInjectedOnOtherContexts(): void
    {
        $this->assertSame('', $this->content('page.header.right'));
    }

    public function testInjectsAStyleAndAScriptOnPageHeaderLeft(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        $this->assertStringContainsString('</style><script>', $content);
        $this->assertStringEndsWith('</script>', $content);
    }

    public function testTheStyleOnlyReachesTheMarkedModalAndUsesMauticTokens(): void
    {
        $css = EmailBuilderErrorModalSubscriber::css();

        $this->assertStringNotContainsString('}.gjs-mdl-dialog{', $css);
        $this->assertSame(0, preg_match('/(^|\})(?!:root \.gjs-mdl-dialog\.mautic-patches-gjs-error)[^{]+\{/', $css), 'every rule starts at the marked dialog');
        $this->assertStringContainsString('var(--background)', $css);
        $this->assertStringContainsString('var(--text-primary)', $css);
    }

    public function testTheScriptSwapsThePluginFooterForMauticsWithTheTranslatedClose(): void
    {
        $script = EmailBuilderErrorModalSubscriber::script('Fechar');

        $this->assertStringContainsString('".panel-footer"', $script, 'only the dialog the plugin built for the error');
        $this->assertStringContainsString('btn btn-tertiary btn-cancel', $script);
        $this->assertStringContainsString('modal-form-buttons', $script);
        $this->assertStringContainsString('ri-close-line', $script);
        $this->assertStringContainsString('"Fechar"', $script);
        $this->assertStringContainsString('[data-close-modal]', $script, 'closes through GrapesJS\' own control');
    }
}
