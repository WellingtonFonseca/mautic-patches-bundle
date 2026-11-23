<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactDetailStatusSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Group('area:contacts')]
#[Group('risk:UI')]
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
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<HEAD>');
        (new ContactDetailStatusSubscriber($translator, $twig))->injectViewCustomContent($event);

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

    public function testThePageGetsTheHeaderComponentBeforeTheScript(): void
    {
        $html = $this->html();

        $this->assertStringStartsWith('<HEAD>', $html, 'the component the script puts in place of core\'s heading');
        $this->assertStringContainsString('installContactDetail', $html);
    }

    public function testTheHeaderStyleSwapsTheBoxSelectorAndAddsTheTitleRule(): void
    {
        $style = ContactDetailStatusSubscriber::headerStyle();

        $this->assertStringNotContainsString('.patches-contact-badges', $style, 'the box of the component is .patches-details-badges');
        $this->assertStringContainsString('.patches-details-badges{display:inline-flex', $style);
        $this->assertStringContainsString('height:40px;min-width:0;max-width:40px', $style);
        $this->assertStringEndsWith(ContactDetailStatusSubscriber::TITLE_STYLE, $style);
        $this->assertStringStartsWith('.patches-contact-header', ContactDetailStatusSubscriber::TITLE_STYLE);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function titles(): iterable
    {
        yield 'record and name' => ['A-100', 'Ana', 'Souza', 'A-100 - Ana Souza'];
        yield 'record only' => ['A-100', '', '', 'A-100'];
        yield 'name only' => ['', 'Ana', 'Souza', 'Ana Souza'];
        yield 'blanks around the record' => ['  A-100  ', 'Ana', '', 'A-100 - Ana'];
        yield 'nothing' => ['', '', '', ''];
    }

    #[DataProvider('titles')]
    public function testHeadHtmlTitleFollowsTheRecordAndTheNameRules(string $record, string $first, string $last, string $expected): void
    {
        $this->assertSame($expected, $this->headVars($this->lead($record, $first, $last))['title']);
    }

    public function testHeadHtmlBlockedListHasEachDoNotContactChannelOnce(): void
    {
        $lead = $this->lead('A-1', 'Ana', 'Souza');
        foreach (['email', 'sms', 'email'] as $channel) {
            $dnc = new DoNotContact();
            $dnc->setChannel($channel);
            $lead->addDoNotContactEntry($dnc);
        }

        $this->assertSame('email,sms', $this->headVars($lead)['blocked']);
        $this->assertSame('', $this->headVars($this->lead('A-1', 'Ana', 'Souza'))['blocked']);
    }

    public function testHeadHtmlRendersTheHeaderTemplateAndReturnsItsHtml(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->with('@MauticPatches/Contact/header.html.twig')->willReturn('<X>');

        $this->assertSame('<X>', ContactDetailStatusSubscriber::headHtml($twig, $this->lead('A-1', 'Ana', 'Souza')));
    }

    /**
     * @return array<string, mixed> the variables the header twig got
     */
    private function headVars(Lead $lead): array
    {
        $vars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$vars): string {
            $vars = $context;

            return '';
        });
        ContactDetailStatusSubscriber::headHtml($twig, $lead);

        return $vars;
    }

    private function lead(string $record, string $first, string $last): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($first);
        $lead->setLastname($last);
        $lead->addUpdatedField('enro_academic_record', $record);

        return $lead;
    }
}
