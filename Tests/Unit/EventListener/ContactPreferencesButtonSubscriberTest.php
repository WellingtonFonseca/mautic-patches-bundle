<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactPreferencesButtonSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactPreferencesButtonSubscriberTest extends TestCase
{
    private CorePermissions&MockObject $security;

    private ContactPreferencesButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            fn (string $route, array $p) => "/s/{$route}/{$p['objectAction']}/{$p['objectId']}"
        );
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $key, array $p = []) => "[{$key}".($p ? ':'.implode(',', $p) : '').']'
        );
        $this->security   = $this->createMock(CorePermissions::class);
        $this->subscriber = new ContactPreferencesButtonSubscriber($router, $translator, $this->security);
    }

    private function contact(): Lead&MockObject
    {
        $contact = $this->createMock(Lead::class);
        $contact->method('getId')->willReturn(9);
        $contact->method('getPrimaryIdentifier')->willReturn('Ana');
        $contact->method('getPermissionUser')->willReturn(3);

        return $contact;
    }

    private function event(string $location, string $route, mixed $item): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new CustomButtonEvent($location, $request, [], $item);
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertSame(['injectViewButtons', 0], ContactPreferencesButtonSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS]);
    }

    public function testAddsPreferencesThatOpenTheCoreModalInTheRowMenuOfTheContactList(): void
    {
        $contact = $this->contact();
        $this->security->expects($this->once())->method('hasEntityAccess')
            ->with('lead:leads:editown', 'lead:leads:editother', 3)->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index', $contact);

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('ajaxmodal', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('#MauticSharedModal', $buttons[0]['attr']['data-target']);
        $this->assertSame('/s/mautic_contact_action/contactFrequency/9', $buttons[0]['attr']['href']);
        $this->assertSame('[mautic.lead.lead.header.contact.frequency:Ana]', $buttons[0]['attr']['data-header']);
        $this->assertSame('[mautic.lead.contact.frequency]', $buttons[0]['btnText']);
    }

    public function testNothingWithoutEditAccess(): void
    {
        $this->security->method('hasEntityAccess')->willReturn(false);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index', $this->contact());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    #[DataProvider('otherPlaces')]
    public function testNothingOutsideTheRowMenuOfTheContactList(string $location, string $route, bool $isContact): void
    {
        $this->security->method('hasEntityAccess')->willReturn(true);
        $event = $this->event($location, $route, $isContact ? $this->contact() : new \stdClass());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public static function otherPlaces(): iterable
    {
        yield 'page actions' => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_index', true];
        yield 'segment contacts table' => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_contacts', true];
        yield 'not a contact' => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index', false];
    }
}
