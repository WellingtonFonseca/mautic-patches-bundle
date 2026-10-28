<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $canEdit = true): CampaignStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn($canEdit);

        return new CampaignStatusSubscriber($translator, $security);
    }

    private function campaign(bool $published): Campaign
    {
        $campaign = new Campaign();
        $campaign->setIsPublished($published);
        (new \ReflectionProperty($campaign, 'id'))->setValue($campaign, 9);

        return $campaign;
    }

    private function buttons(CampaignStatusSubscriber $subscriber, Campaign $campaign, string $route = 'mautic_campaign_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $campaign);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    private function labelHtml(Campaign $campaign): string
    {
        $event = new CustomContentEvent('some_view', 'campaign.name', ['item' => $campaign]);
        $this->subscriber()->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testACampaignThatAllowsReentryCarriesTheMarkerForTheReentryBadge(): void
    {
        $allows = $this->campaign(true);
        $allows->setAllowRestart(true);
        $denies = $this->campaign(true);
        $denies->setAllowRestart(false);

        $this->assertStringContainsString(' data-restart="1"', $this->labelHtml($allows));
        $this->assertStringNotContainsString('data-restart', $this->labelHtml($denies));
    }

    public function testSubscribesToTheContentAndTheButtons(): void
    {
        $events = CampaignStatusSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, $events);
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, $events);
    }

    public function testTheLabelTellsTheStatusAndIsNotALinkItself(): void
    {
        foreach ([[true, 'patches-badge--production', 'published', 'mautic.core.form.active'], [false, 'patches-badge--failed', 'unpublished', 'mautic.core.form.inactive']] as [$published, $class, $status, $text]) {
            $event = new CustomContentEvent('some_view', 'campaign.name', ['item' => $this->campaign($published)]);
            $this->subscriber()->injectViewCustomContent($event);
            $html = implode('', $event->getContent());

            $this->assertStringContainsString($class.' patches-status-label', $html);
            $this->assertStringContainsString('data-status="'.$status.'"', $html);
            $this->assertStringContainsString('data-campaign-id="9"', $html);
            $this->assertStringContainsString('>'.$text.'<', $html);
            $this->assertStringNotContainsString('<a', $html);
        }
    }

    public function testTheScriptIsOnPageHeaderLeftOnlyAndNothingWithoutACampaign(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesListStatusApi.installCampaign(mQuery,Mautic,', implode('', $event->getContent()));

        foreach ([new CustomContentEvent('v', 'campaign.name', []), new CustomContentEvent('v', 'campaign.name', ['item' => 'no']), new CustomContentEvent('v', 'something.else', ['item' => $this->campaign(true)])] as $other) {
            $this->subscriber()->injectViewCustomContent($other);
            $this->assertSame([], $other->getContent());
        }
    }

    public function testTheCoreIconIsHiddenInTheRowsAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = CampaignStatusSubscriber::STYLE;
        $this->assertStringContainsString('tr i[class*="campaign-publish-icon"]{display:none!important}', $css);
        $this->assertStringContainsString('[data-campaign-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-campaign-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testTheMenuOffersToDeactivateAnActiveCampaignAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->campaign(true))[0];
        $this->assertSame('deactivate', $active['attr']['data-campaign-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active['btnText']);

        $inactive = $this->buttons($this->subscriber(), $this->campaign(false))[0];
        $this->assertSame('activate', $inactive['attr']['data-campaign-status-action']);
        $this->assertSame('mautic.patches.segment.activate', $inactive['btnText']);
    }

    public function testTheItemIsFirstInTheMenuAndCoreDoesNotMakeItANavigationLink(): void
    {
        $button = $this->buttons($this->subscriber(), $this->campaign(true))[0];

        $this->assertGreaterThan(250, $button['priority']);
        $this->assertSame('campaign-status', $button['attr']['data-toggle']);
        $this->assertSame(9, $button['attr']['data-campaign-id']);
    }

    public function testNoItemWithoutTheEditPermissionOrOnAnotherRouteOrLocation(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->campaign(true)));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->campaign(true), 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->campaign(true), 'mautic_campaign_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }

    public function testThePermissionAskedIsTheCampaignsEditOwnOrOther(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->expects($this->once())->method('hasEntityAccess')->with('campaign:campaigns:editown', 'campaign:campaigns:editother', $this->anything())->willReturn(true);
        $request = new Request();
        $request->attributes->set('_route', 'mautic_campaign_index');
        $event = new CustomButtonEvent(ButtonHelper::LOCATION_LIST_ACTIONS, $request, [], $this->campaign(true));

        (new CampaignStatusSubscriber($translator, $security))->injectViewButtons($event);

        $this->assertCount(1, $event->getButtons());
    }

    public function testAnItemThatIsNotACampaignGetsNoMenuItem(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'mautic_campaign_index');
        $buttons = new CustomButtonEvent(ButtonHelper::LOCATION_LIST_ACTIONS, $request, [], new \Mautic\LeadBundle\Entity\LeadList());
        $this->subscriber()->injectViewButtons($buttons);

        $this->assertSame([], $buttons->getButtons());
    }

    public function testTheHeaderScriptCarriesTheStyleOfTheList(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);

        $this->assertStringContainsString('campaign-publish-icon', implode('', $event->getContent()));
    }
}
