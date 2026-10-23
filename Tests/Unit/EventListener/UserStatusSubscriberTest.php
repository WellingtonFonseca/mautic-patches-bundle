<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\EventListener\UserStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $canEdit = true, int $loggedId = 1): UserStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->method('isGranted')->willReturnCallback(fn ($permission): bool => 'user:users:edit' === $permission && $canEdit);
        $userHelper = $this->createMock(UserHelper::class);
        $userHelper->method('getUser')->willReturn($this->user(true, $loggedId));

        return new UserStatusSubscriber($translator, $security, $userHelper);
    }

    private function user(bool $published, int $id = 9): User
    {
        $user = new User();
        $user->setIsPublished($published);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return $user;
    }

    private function buttons(UserStatusSubscriber $subscriber, User $user, string $route = 'mautic_user_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $user);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testSubscribesToTheContentAndTheButtons(): void
    {
        $events = UserStatusSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, $events);
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, $events);
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesSegmentStatusApi.installUser(mQuery,Mautic,', implode('', $event->getContent()));

        $other = new CustomContentEvent('some_view', 'user.name', ['item' => $this->user(true)]);
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheCoreIconIsHiddenInTheUserTableAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = UserStatusSubscriber::STYLE;
        $this->assertStringContainsString('#userTable i[class*="user-publish-icon"]{display:none!important}', $css);
        $this->assertStringContainsString('[data-user-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-user-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testTheMenuOffersToDeactivateAnActiveUserAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->user(true))[0];
        $this->assertSame('deactivate', $active['attr']['data-user-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active['btnText']);

        $inactive = $this->buttons($this->subscriber(), $this->user(false))[0];
        $this->assertSame('activate', $inactive['attr']['data-user-status-action']);
        $this->assertSame('mautic.patches.segment.activate', $inactive['btnText']);
    }

    public function testTheItemIsFirstInTheMenuAndCoreDoesNotMakeItANavigationLink(): void
    {
        $button = $this->buttons($this->subscriber(), $this->user(true))[0];

        $this->assertGreaterThan(250, $button['priority']);
        $this->assertSame('user-status', $button['attr']['data-toggle']);
        $this->assertSame(9, $button['attr']['data-user-id']);
    }

    public function testNoItemWithoutTheEditPermissionForTheLoggedUserOrOnAnotherRouteOrLocation(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->user(true)));
        $this->assertSame([], $this->buttons($this->subscriber(true, 9), $this->user(true, 9)), 'nobody turns oneself off');
        $this->assertSame([], $this->buttons($this->subscriber(), $this->user(true), 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->user(true), 'mautic_user_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }
}
