<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\FieldDefinitionButtonSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldDefinitionButtonSubscriberTest extends TestCase
{
    private function subscriber(bool $granted): FieldDefinitionButtonSubscriber
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $route): string => '/url/'.$route);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id): string => $id);
        $security = $this->createStub(CorePermissions::class);
        // a stub cannot use with() on 12: the answer is yes only for the permission of the fields
        $security->method('isGranted')->willReturnCallback(fn (string $permission): bool => $granted && 'lead:fields:full' === $permission);

        return new FieldDefinitionButtonSubscriber($router, $translator, $security);
    }

    private function buttons(FieldDefinitionButtonSubscriber $subscriber, string $location, string $route): array
    {
        $event = new CustomButtonEvent($location, new Request([], [], ['_route' => $route]));
        $subscriber->injectViewButtons($event);

        return $event->getButtons();
    }

    public function testItListensToTheButtonsEvent(): void
    {
        $this->assertSame([CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0]], FieldDefinitionButtonSubscriber::getSubscribedEvents());
    }

    public function testTheFieldListGetsExportAndImportForWhoManagesFields(): void
    {
        $buttons = $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contactfield_index');

        $this->assertCount(2, $buttons);
        $this->assertSame(
            ['/url/mautic_patches_field_definition_export', '/url/mautic_patches_field_definition_import'],
            array_map(fn (array $b): string => $b['attr']['href'], array_values($buttons))
        );
        $this->assertSame([200, 190], array_map(fn (array $b): int => $b['priority'], array_values($buttons)));
        $this->assertSame(['ajax', 'ajax'], array_map(fn (array $b): string => $b['attr']['data-toggle'], array_values($buttons)));
        $this->assertSame(
            ['mautic.patches.field_definition.button.export', 'mautic.patches.field_definition.button.import'],
            array_map(fn (array $b): string => $b['btnText'], array_values($buttons))
        );
    }

    public function testNothingForWhoCannotManageFieldsAnotherRouteOrAnotherLocation(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contactfield_index'));
        $this->assertSame([], $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(true), ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contactfield_index'));
    }
}
