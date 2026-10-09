<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactListClickSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactListClickSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('mautic_patches_contact_details', ['id' => 0])->willReturn('/s/contact-details/0');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $event = new CustomContentEvent('some_view', $context);
        (new ContactListClickSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ContactListClickSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptKnowsTheModalRouteAndTheTitle(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('"/s/contact-details/"+m[1]', $js);
        $this->assertStringContainsString('"[mautic.patches.contact.view.title]"', $js);
        $this->assertStringContainsString('Mautic.ajaxifyModal(el,event);', $js);
    }

    public function testItRunsBeforeCoresAjaxLinkHandlerAndOnlyInTheContactTable(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('},true);', $js, 'capture phase');
        $this->assertStringContainsString('"#leadTable a[href]"', $js);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $js);
    }
}
