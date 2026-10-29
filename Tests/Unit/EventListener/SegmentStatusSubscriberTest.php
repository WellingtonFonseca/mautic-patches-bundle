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
use MauticPlugin\MauticPatchesBundle\EventListener\AbstractListStatusSubscriber;
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

            $this->assertStringContainsString($class.' patches-status-label', $html);
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
        $this->assertStringContainsString('mauticPatchesListStatusApi.install(mQuery,Mautic,', $html);

        $other = new CustomContentEvent('some_view', 'something.else');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheIconIsHiddenAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = SegmentStatusSubscriber::STYLE;
        $this->assertStringContainsString('i[class*="lead-list-publish-icon"]{display:none!important}', $css);
        // the description under the name is hidden in the lists whose label moved to the Status column (segment, campaign, email)
        $this->assertStringContainsString('#leadListTable td > div.text-muted,#campaignTable td > div.text-muted,table.email-list td > div.text-muted{display:none!important}', $css);
        // the icon badge of the Status column is part of the style, with no fixed width
        $this->assertStringContainsString('td.col-patches-status :is(.patches-status-label,.patches-extra-badge){position:relative;z-index:2;flex:none;display:inline-flex;', $css);
        $this->assertStringContainsString('max-width:22px;', $css);
        // the badges sit in a box that keeps the table from reflowing while one opens
        $this->assertStringContainsString('td.col-patches-status .patches-status-box{display:flex;align-items:center;gap:6px;height:22px}', $css);
        // the icons of core next to the name are hidden from the first paint, and the badges beside the status are blue (flag) or get more room (wide)
        $this->assertStringContainsString('#leadListTable td:not(.col-patches-status) :is(i.ri-group-2-fill,i.ri-filter-2-fill,label:has(>i.ri-error-warning-line-circle),label:has(>i.ri-time-line)){display:none!important}', $css);
        $this->assertStringContainsString('.patches-badge--flag,.patches-badge--restart{background-color:#1e88e5}', $css);
        $this->assertStringContainsString('.patches-extra-badge--wide:hover{max-width:260px}', $css);
        // "Atualizado em ..." is as large as the table
        $this->assertStringContainsString('.segment-last-built small{font-size:14px}', $css);
        // the contact count is a translucent badge of the same family
        $this->assertStringContainsString('.patches-badge--info{', $css);
        $this->assertStringContainsString('.patches-badge--neutral{', $css);
        $this->assertStringContainsString('text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}', $css, 'the status badge is the twin of the n8n badge');
        $this->assertStringContainsString('.patches-badge--failed{background-color:#e53935}', $css);
        // the cells of the lists never wrap, so an open badge widens its column and no row changes height under the pointer
        $this->assertStringContainsString('#leadListTable td,#campaignTable td,#userTable td,table.email-list td,#leadTable td{white-space:nowrap}', $css);
        // the email list: one color for the type (campaign and broadcast), one for each of A/B test, translation and draft
        $this->assertStringContainsString('.patches-badge--emailtype{background-color:#00897b}.patches-badge--abtest{background-color:#7e57c2}', $css);
        $this->assertStringContainsString('.patches-badge--translation{background-color:#4285f4}.patches-badge--draft{background-color:#546e7a}', $css);
        // the script puts the label at the start of the name; until then it is invisible
        $this->assertStringContainsString('.patches-status-label:not([data-placed]){visibility:hidden}', $css);
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

    public function testThePermissionAskedIsTheSegmentsEditOwnOrOther(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->expects($this->once())->method('hasEntityAccess')->with('lead:lists:editown', 'lead:lists:editother', $this->anything())->willReturn(true);
        $request = new Request();
        $request->attributes->set('_route', 'mautic_segment_index');
        $event = new CustomButtonEvent(ButtonHelper::LOCATION_LIST_ACTIONS, $request, [], $this->segment(true));

        (new SegmentStatusSubscriber($translator, $security))->injectViewButtons($event);

        $this->assertCount(1, $event->getButtons());
    }

    public function testAnItemThatIsNotASegmentGetsNoLabelAndNoMenuItem(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'mautic_segment_index');
        $buttons = new CustomButtonEvent(ButtonHelper::LOCATION_LIST_ACTIONS, $request, [], new \Mautic\CampaignBundle\Entity\Campaign());
        $this->subscriber()->injectViewButtons($buttons);
        $this->assertSame([], $buttons->getButtons());

        $content = new CustomContentEvent('some_view', 'segment.name', ['item' => new \Mautic\CampaignBundle\Entity\Campaign()]);
        $this->subscriber()->injectViewCustomContent($content);
        $this->assertSame([], $content->getContent());
    }

    public function testTheHeaderScriptCarriesTheStyleOfTheListAndTheBadgeStyle(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $html = implode('', $event->getContent());

        $this->assertStringContainsString('lead-list-publish-icon', $html);
        $this->assertStringContainsString('.patches-badge--production', $html);
    }

    public function testTheBadgeStyleKeepsTheStatusColorsAndIsPartOfTheSegmentStyle(): void
    {
        $this->assertStringContainsString('.patches-badge--production{background-color:#43a047}', AbstractListStatusSubscriber::BADGE_STYLE);
        $this->assertStringContainsString('.patches-badge--failed{background-color:#e53935}', AbstractListStatusSubscriber::BADGE_STYLE);
        $this->assertStringStartsWith(AbstractListStatusSubscriber::BADGE_STYLE, SegmentStatusSubscriber::STYLE);
    }
}
