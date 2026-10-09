<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactListViewButtonSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactListViewButtonSubscriberTest extends TestCase
{
    private ContactListViewButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $route, array $p) => "/s/{$route}/{$p['id']}");
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $this->subscriber = new ContactListViewButtonSubscriber($router, $translator);
    }

    private function event(string $location, string $route, mixed $item): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new CustomButtonEvent($location, $request, [], $item);
    }

    private function contact(): Lead
    {
        $contact = $this->createMock(Lead::class);
        $contact->method('getId')->willReturn(185);

        return $contact;
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, ContactListViewButtonSubscriber::getSubscribedEvents());
    }

    public function testAddsViewToTheRowMenuOfTheContactListAsAModal(): void
    {
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index', $this->contact());

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.patches.contact.view]', $buttons[0]['btnText']);
        $this->assertSame('/s/mautic_patches_contact_details/185', $buttons[0]['attr']['href']);
        $this->assertSame('ajaxmodal', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('#MauticSharedModal', $buttons[0]['attr']['data-target']);
        $this->assertSame('[mautic.patches.contact.view.title]', $buttons[0]['attr']['data-header']);
    }

    public function testStaysOutOfOtherListsAndPages(): void
    {
        foreach ([
            [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_segment_index', $this->contact()],
            [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_index', $this->contact()],
            [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index', $this->createMock(LeadList::class)],
        ] as [$location, $route, $item]) {
            $event = $this->event($location, $route, $item);
            $this->subscriber->injectViewButtons($event);
            $this->assertSame([], $event->getButtons());
        }
    }
}
