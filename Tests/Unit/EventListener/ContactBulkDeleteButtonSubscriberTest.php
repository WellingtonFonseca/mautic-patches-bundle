<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactBulkDeleteButtonSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactBulkDeleteButtonSubscriberTest extends TestCase
{
    private function buttons(bool $granted, string $location, string $route): array
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $name): string => '/url/'.$name);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id): string => $id);
        $security = $this->createMock(CorePermissions::class);
        $security->method('isGranted')->with('lead:leads:deleteother')->willReturn($granted);

        $event = new CustomButtonEvent($location, new Request([], [], ['_route' => $route]));
        (new ContactBulkDeleteButtonSubscriber($router, $translator, $security))->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testItListensToTheButtonsEvent(): void
    {
        $this->assertSame([CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0]], ContactBulkDeleteButtonSubscriber::getSubscribedEvents());
    }

    public function testTheContactListGetsTheRedOptionLastForWhoMayDelete(): void
    {
        $buttons = $this->buttons(true, ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_index');

        $this->assertCount(1, $buttons);
        $this->assertSame('/url/mautic_patches_contact_bulk_delete', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('danger', $buttons[0]['attr']['class']);
        $this->assertSame('mautic.patches.contact_bulk_delete.menu', $buttons[0]['btnText']);
        $this->assertLessThan(0, $buttons[0]['priority']);
    }

    public function testNothingWithoutPermissionOnAnotherRouteOrAnotherLocation(): void
    {
        $this->assertSame([], $this->buttons(false, ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_index'));
        $this->assertSame([], $this->buttons(true, ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_index'));
        $this->assertSame([], $this->buttons(true, ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index'));
    }
}
