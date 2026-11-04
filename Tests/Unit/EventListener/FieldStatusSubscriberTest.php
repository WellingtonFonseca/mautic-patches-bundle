<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadField;
use MauticPlugin\MauticPatchesBundle\EventListener\AbstractListStatusSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\FieldStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $granted = true): FieldStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->method('isGranted')->with('lead:fields:full')->willReturn($granted);

        return new FieldStatusSubscriber($translator, $security);
    }

    private function field(bool $published, string $alias = 'city', int $id = 9): LeadField
    {
        $field = new LeadField();
        $field->setAlias($alias);
        $field->setIsPublished($published);
        (new \ReflectionProperty($field, 'id'))->setValue($field, $id);

        return $field;
    }

    private function buttons(FieldStatusSubscriber $subscriber, object $item, string $route = 'mautic_contactfield_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $item);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesListStatusApi.installField(mQuery,Mautic,', implode('', $event->getContent()));

        $other = new CustomContentEvent('some_view', 'field.name');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheCoreIconIsHiddenInTheFieldTableAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = FieldStatusSubscriber::STYLE;

        $this->assertStringContainsString('#leadFieldTable i[class*="lead-field-publish-icon"]{display:none!important}', $css);
        $this->assertStringContainsString('[data-field-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-field-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testALockedFieldBadgeIsGrayAndTheFieldTableCellsDoNotWrap(): void
    {
        $this->assertStringContainsString('.patches-badge--locked{background-color:#9e9e9e}', AbstractListStatusSubscriber::ICON_BADGE_STYLE);
        $this->assertStringContainsString('#leadFieldTable td{white-space:nowrap}', AbstractListStatusSubscriber::ICON_BADGE_STYLE);
    }

    public function testTheMenuOffersToDeactivateAnActiveFieldAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->field(true))[0];
        $this->assertSame('deactivate', $active['attr']['data-field-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active['btnText']);
        $this->assertSame(9, $active['attr']['data-field-id']);
        $this->assertGreaterThan(250, $active['priority']);

        $inactive = $this->buttons($this->subscriber(), $this->field(false))[0];
        $this->assertSame('activate', $inactive['attr']['data-field-status-action']);
    }

    public function testAFixedFieldCanBeDeactivatedLikeCoreAllows(): void
    {
        $field = $this->field(true);
        $field->setIsFixed(true);
        $field->setIsUniqueIdentifier(true);

        $this->assertCount(1, $this->buttons($this->subscriber(), $field));
    }

    public function testTheEmailFieldAndAFieldWhoseColumnIsBeingMadeGetNoMenuItem(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(), $this->field(true, 'email')));

        $building = $this->field(true);
        $building->setColumnIsNotCreated();
        $this->assertSame([], $this->buttons($this->subscriber(), $building));
    }

    public function testNoItemWithoutThePermissionOrForOtherItemsRoutesOrLocations(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->field(true)));
        $this->assertSame([], $this->buttons($this->subscriber(), new \stdClass()));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->field(true), 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->field(true), 'mautic_contactfield_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }
}
