<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticPatchesBundle\EventListener\RoleUserCloneButtonSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleUserCloneButtonSubscriberTest extends TestCase
{
    private function subscriber(bool $admin): RoleUserCloneButtonSubscriber
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            fn (string $route, array $p): string => '/url/'.$route.'/'.$p['kind'].(isset($p['ids']) ? '?ids='.$p['ids'] : '')
        );
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id): string => $id);
        $security = $this->createMock(CorePermissions::class);
        $security->method('isAdmin')->willReturn($admin);

        return new RoleUserCloneButtonSubscriber($router, $translator, $security);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buttons(RoleUserCloneButtonSubscriber $subscriber, string $location, string $route, mixed $item = null): array
    {
        $event = new CustomButtonEvent($location, new Request([], [], ['_route' => $route]), [], $item);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    private function entity(string $class, int $id): object
    {
        $entity = new $class();
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);

        return $entity;
    }

    public function testItListensToTheButtonsEvent(): void
    {
        $this->assertSame([CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0]], RoleUserCloneButtonSubscriber::getSubscribedEvents());
    }

    #[DataProvider('lists')]
    public function testTheGearGetsExportAllInTheModalAndImportAsAPage(string $route, string $kind): void
    {
        $buttons = $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_PAGE_ACTIONS, $route);

        $this->assertCount(2, $buttons);
        $this->assertSame(['/url/mautic_patches_role_user_clone_export/'.$kind, '/url/mautic_patches_role_user_clone_import/'.$kind], array_column(array_column($buttons, 'attr'), 'href'));
        $this->assertSame(['mautic.patches.role_user_clone.button.export_all.'.$kind, 'mautic.patches.role_user_clone.button.import.'.$kind], array_column($buttons, 'btnText'));
        $this->assertSame([200, 190], array_column($buttons, 'priority'));
        $this->assertSame(['ri-download-2-line', 'ri-upload-2-line'], array_column($buttons, 'iconClass'));
        $this->assertSame(['ajaxmodal', 'ajax'], array_column(array_column($buttons, 'attr'), 'data-toggle'));
        $this->assertSame('#MauticSharedModal', $buttons[0]['attr']['data-target']);
        $this->assertSame('mautic.patches.role_user_clone.export.title.'.$kind, $buttons[0]['attr']['data-header']);
        $this->assertArrayNotHasKey('data-target', $buttons[1]['attr']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function lists(): array
    {
        return ['roles' => ['mautic_role_index', 'roles'], 'users' => ['mautic_user_index', 'users']];
    }

    #[DataProvider('rows')]
    public function testEachRowGetsItsOwnExportInTheModal(string $route, string $kind, string $class): void
    {
        $buttons = $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_LIST_ACTIONS, $route, $this->entity($class, 42));

        $this->assertCount(1, $buttons);
        $attr = $buttons[0]['attr'];
        $this->assertSame('/url/mautic_patches_role_user_clone_export/'.$kind.'?ids=[42]', $attr['href']);
        $this->assertSame('ajaxmodal', $attr['data-toggle']);
        $this->assertSame('#MauticSharedModal', $attr['data-target']);
        $this->assertSame('mautic.patches.role_user_clone.export.title.'.$kind, $attr['data-header']);
        $this->assertSame('mautic.patches.role_user_clone.button.export_one.'.$kind, $buttons[0]['btnText']);
        $this->assertSame('ri-download-2-line', $buttons[0]['iconClass']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function rows(): array
    {
        return ['roles' => ['mautic_role_index', 'roles', Role::class], 'users' => ['mautic_user_index', 'users', User::class]];
    }

    public function testARowOfAnotherKindOfItemGetsNothing(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_user_index', new \stdClass()));
        $this->assertSame([], $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_user_index'));
    }

    #[DataProvider('tables')]
    public function testTheBulkMenuGetsOneButtonThatOpensTheModalWithTheCheckedRows(string $route, string $kind, string $table): void
    {
        $buttons = $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_BULK_ACTIONS, $route);

        $this->assertCount(1, $buttons);
        $attr = $buttons[0]['attr'];
        $this->assertSame('ajaxmodal', $attr['data-toggle']);
        $this->assertSame('#MauticSharedModal', $attr['data-target']);
        $this->assertSame('/url/mautic_patches_role_user_clone_export/'.$kind, $attr['data-base']);
        $this->assertSame($attr['data-base'], $attr['href']);
        $this->assertSame(
            "this.href = this.getAttribute('data-base') + '?ids=' + encodeURIComponent(JSON.stringify(mQuery('".$table." input[class=list-checkbox]:checked').map(function () { return mQuery(this).val(); }).get()));",
            $attr['onclick']
        );
        $this->assertSame('mautic.patches.role_user_clone.button.export_selected.'.$kind, $buttons[0]['btnText']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function tables(): array
    {
        return ['roles' => ['mautic_role_index', 'roles', '#roleTable'], 'users' => ['mautic_user_index', 'users', '#userTable']];
    }

    public function testNothingForANonAdministratorAnotherRouteOrAnotherLocation(): void
    {
        $role = $this->entity(Role::class, 1);
        foreach ([ButtonHelper::LOCATION_PAGE_ACTIONS, ButtonHelper::LOCATION_BULK_ACTIONS, ButtonHelper::LOCATION_LIST_ACTIONS] as $location) {
            $this->assertSame([], $this->buttons($this->subscriber(false), $location, 'mautic_role_index', $role), $location);
            $this->assertSame([], $this->buttons($this->subscriber(true), $location, 'mautic_segment_index', $role), $location);
        }
        $this->assertSame([], $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_NAVBAR, 'mautic_user_index'));
    }
}
