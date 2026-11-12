<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\AppearanceButtonSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppearanceButtonSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('mautic_patches_appearance_modal')->willReturn('/s/appearance-modal');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('mautic.user.account.appearance')->willReturn('Aparência');

        $event = new CustomContentEvent('some_view', $context);
        (new AppearanceButtonSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, AppearanceButtonSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('<script>', $js);
        $this->assertStringEndsWith('</script>', $js);
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptGetsTheModalUrlAndTheTranslatedLabel(): void
    {
        $this->assertStringContainsString(
            'install(mQuery,Mautic,document,window.localStorage,{"url":"/s/appearance-modal","label":"Aparência"});',
            $this->content()
        );
    }

    public function testTheButtonIsHiddenWhileTheAccountPageIsShown(): void
    {
        $this->assertStringContainsString(
            '<style>body:has(#appearance.tab-pane) .patches-appearance-button{display:none}</style>',
            $this->content()
        );
    }
}
