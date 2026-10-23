<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $canEdit = true): SegmentStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn($canEdit);

        return new SegmentStatusSubscriber($translator, $security);
    }

    private function segment(bool $published): LeadList
    {
        $segment = new LeadList();
        $segment->setIsPublished($published);
        $segment->setAlias('seg-7');
        (new \ReflectionProperty($segment, 'id'))->setValue($segment, 7);

        return $segment;
    }

    private function buttons(SegmentStatusSubscriber $subscriber, LeadList $segment, string $route = 'mautic_segment_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $segment);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testSubscribesToTheContentAndTheButtons(): void
    {
        $events = SegmentStatusSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, $events);
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, $events);
    }

    public function testTheLabelTellsTheStatusAndIsNotALink(): void
    {
        foreach ([[true, 'patches-badge--production', 'published', 'mautic.core.form.active'], [false, 'patches-badge--failed', 'unpublished', 'mautic.core.form.inactive']] as [$published, $class, $status, $text]) {
            $event = new CustomContentEvent('some_view', 'segment.name', ['item' => $this->segment($published)]);
            $this->subscriber()->injectViewCustomContent($event);
            $html = implode('', $event->getContent());

            $this->assertStringContainsString($class.' segment-status-label', $html);
            $this->assertStringContainsString('data-status="'.$status.'"', $html);
            $this->assertStringContainsString('data-segment-id="7"', $html);
            // the alias goes with the label: the script takes "(alias)" off the name
            $this->assertStringContainsString('data-alias="seg-7"', $html);
            $this->assertStringContainsString('>'.$text.'<', $html);
            $this->assertStringStartsWith('<span', $html);
            $this->assertStringNotContainsString('<a', $html);
        }
    }

    public function testTheScriptAndTheStyleAreOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $html = implode('', $event->getContent());
        $this->assertStringContainsString('mauticPatchesSegmentStatusApi.install(mQuery,Mautic,', $html);

        $other = new CustomContentEvent('some_view', 'something.else');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheIconIsHiddenAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = SegmentStatusSubscriber::STYLE;
        $this->assertStringContainsString('i[class*="lead-list-publish-icon"]{display:none!important}', $css);
        // the description under the name is hidden, only in the rows that have the label (segment and campaign lists)
        $this->assertStringContainsString('td:has(.segment-status-label) > div:is(.text-muted,.text-secondary){display:none!important}', $css);
        // "Atualizado em ..." is as large as the table
        $this->assertStringContainsString('.segment-last-built small{font-size:14px}', $css);
        // the contact count is a translucent badge of the same family
        $this->assertStringContainsString('.patches-badge--info{', $css);
        $this->assertStringContainsString('.patches-badge--neutral{', $css);
        $this->assertStringContainsString('text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}', $css, 'the status badge is the twin of the n8n badge');
        $this->assertStringContainsString('.patches-badge--failed{background-color:#e53935}', $css);
        // the script puts the label at the start of the name; until then it is invisible
        $this->assertStringContainsString('.segment-status-label:not([data-placed]){visibility:hidden}', $css);
        // all the labels have the same width
        $this->assertStringContainsString('min-width:76px}', $css);
        $this->assertStringContainsString('[data-segment-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-segment-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testTheMenuOffersToDeactivateAnActiveSegmentAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->segment(true));
        $this->assertSame('deactivate', $active[0]['attr']['data-segment-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active[0]['btnText']);

        $inactive = $this->buttons($this->subscriber(), $this->segment(false));
        $this->assertSame('activate', $inactive[0]['attr']['data-segment-status-action']);
        $this->assertSame('mautic.patches.segment.activate', $inactive[0]['btnText']);
    }

    public function testTheItemIsFirstInTheMenuAndCoreDoesNotMakeItANavigationLink(): void
    {
        $button = $this->buttons($this->subscriber(), $this->segment(true))[0];

        // above Details (250); Delete is added by core with no priority, at the end
        $this->assertGreaterThan(250, $button['priority']);
        // core adds data-toggle="ajax" when it is missing: the script sends the request itself
        $this->assertSame('segment-status', $button['attr']['data-toggle']);
        $this->assertSame(7, $button['attr']['data-segment-id']);
    }

    public function testNoItemWithoutTheEditPermissionOrOnAnotherRouteOrLocation(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->segment(true)));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->segment(true), 'mautic_campaign_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->segment(true), 'mautic_segment_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }
}
