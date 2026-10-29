<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactStatusSubscriberTest extends TestCase
{
    private function subscriber(): ContactStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ContactStatusSubscriber($translator);
    }

    public function testSubscribesToTheContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ContactStatusSubscriber::getSubscribedEvents());
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesListStatusApi.installContact(mQuery,Mautic,', implode('', $event->getContent()));

        $other = new CustomContentEvent('some_view', 'contact.name');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheTextsAreInTheConfigOfTheScript(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $html = implode('', $event->getContent());

        foreach (['contactEmail', 'contactSms', 'contactHsm'] as $key) {
            $this->assertStringContainsString('"'.$key.'"', $html);
        }
    }

    public function testCoresRedLabelsOfEmailAndSmsAreHiddenOnlyInATableWithTheColumn(): void
    {
        $this->assertStringContainsString('#leadTable:has(th.col-contact-status) td:not(.col-patches-status) .label-danger:has(>i.ico-email,>i.ico-sms){display:none!important}', ContactStatusSubscriber::STYLE);
    }
}
