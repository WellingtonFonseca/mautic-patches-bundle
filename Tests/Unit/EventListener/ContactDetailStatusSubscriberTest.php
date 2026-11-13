<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactDetailStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactDetailStatusSubscriberTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $vars null = the vars of a contact page (core passes the contact as 'lead')
     */
    private function html(string $context = 'page.header.left', ?array $vars = null): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $event = new CustomContentEvent('some_view', $context, $vars ?? ['lead' => new Lead()]);
        (new ContactDetailStatusSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToTheContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, ContactDetailStatusSubscriber::getSubscribedEvents());
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $this->assertStringContainsString('mauticPatchesListStatusApi.installContactDetail(mQuery,Mautic,', $this->html());
        $this->assertSame('', $this->html('contact.name'));
    }

    public function testAHeaderWithoutAContactGetsNothing(): void
    {
        $this->assertSame('', $this->html('page.header.left', []), 'a contact list or another page also fires page.header.left');
        $this->assertSame('', $this->html('page.header.left', ['lead' => 'not a contact']));
    }

    public function testTheDetailScriptLoadsAfterTheListScript(): void
    {
        $html = $this->html();

        $this->assertLessThan(strpos($html, 'contact-detail-badges.js'), strpos($html, 'list-status-toggle.js'));
    }

    public function testTheTextsAndTheStyleAreInTheConfigOfTheScript(): void
    {
        $html = $this->html();

        foreach (['contactEmail', 'contactSms', 'contactHsm', 'style'] as $key) {
            $this->assertStringContainsString('"'.$key.'"', $html);
        }
    }

    public function testTheBadgeIsAsTallAsTheRoundButtonWithTheIconAndWordOfTheButtonsTextSize(): void
    {
        $style = ContactDetailStatusSubscriber::STYLE;

        $this->assertStringContainsString('height:40px;min-width:0;max-width:40px', $style, 'a closed badge is a 40px circle');
        $this->assertStringContainsString('font-size:14px', $style, 'the text size of core\'s buttons');
        $this->assertStringContainsString('flex:0 0 40px;width:40px;height:40px', $style, 'the icon box fills the circle');
        $this->assertStringContainsString('max-width:140px', $style, 'the open badge: the width of the list');
        $this->assertStringContainsString('border-radius:20px', $style);
        $this->assertStringContainsString('gap:8px', $style, 'the gap of the toolbar buttons');
        $this->assertStringContainsString('margin-right:5px', $style);
    }

    public function testEveryRuleIsScopedToTheBox(): void
    {
        foreach (explode('}', rtrim(ContactDetailStatusSubscriber::STYLE, '}')) as $rule) {
            $this->assertStringStartsWith('.patches-contact-badges', $rule, 'a rule outside the box would resize the badges of the lists');
        }
    }
}
