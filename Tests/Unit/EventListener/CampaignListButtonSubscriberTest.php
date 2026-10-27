<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignListButtonSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignRebuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignListButtonSubscriberTest extends TestCase
{
    private CampaignRebuilder&MockObject $rebuilder;

    private CampaignListButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            fn (string $route, array $p) => "/s/{$route}/{$p['id']}".(isset($p['return']) ? "?return={$p['return']}" : '')
        );
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $this->rebuilder  = $this->createMock(CampaignRebuilder::class);
        $this->subscriber = new CampaignListButtonSubscriber($router, $translator, $this->rebuilder);
    }

    private function event(string $location, string $route, mixed $item, ?string $objectAction = null): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        if (null !== $objectAction) {
            $request->attributes->set('objectAction', $objectAction);
        }

        return new CustomButtonEvent($location, $request, [], $item);
    }

    private function campaign(): Campaign&MockObject
    {
        $campaign = $this->createMock(Campaign::class);
        $campaign->method('getId')->willReturn(7);

        return $campaign;
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertSame(['injectViewButtons', 0], CampaignListButtonSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS]);
    }

    public function testAddsUpdateToTheRowMenuOfTheCampaignList(): void
    {
        $campaign = $this->campaign();
        $campaign->method('isPublished')->willReturn(true);
        $this->rebuilder->method('hasEditAccess')->with($campaign)->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', $campaign);

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.patches.campaign.rebuild]', $buttons[0]['btnText']);
        $this->assertSame('/s/mautic_patches_campaign_rebuild/7', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('POST', $buttons[0]['attr']['data-method']);
        $this->assertArrayNotHasKey('hidden', $buttons[0]['attr']);
        $this->assertSame(150, $buttons[0]['priority']);
    }

    public function testTheRowMenuCarriesUpdateHiddenForAnInactiveCampaignSoActivatingCanShowIt(): void
    {
        $campaign = $this->campaign();
        $campaign->method('isPublished')->willReturn(false);
        $this->rebuilder->method('hasEditAccess')->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', $campaign);

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('hidden', $buttons[0]['attr']['hidden']);
        $this->assertSame('/s/mautic_patches_campaign_rebuild/7', $buttons[0]['attr']['href']);
    }

    public function testAddsUpdateToThePageActionsOfTheCampaignDetailAndComesBackThere(): void
    {
        $campaign = $this->campaign();
        $this->rebuilder->method('canRebuild')->with($campaign)->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_campaign_action', $campaign, 'view');

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.patches.campaign.rebuild]', $buttons[0]['btnText']);
        $this->assertSame('/s/mautic_patches_campaign_rebuild/7?return=view', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('POST', $buttons[0]['attr']['data-method']);
        $this->assertArrayNotHasKey('hidden', $buttons[0]['attr']);
        $this->assertSame(150, $buttons[0]['priority']);
    }

    public function testDetailButtonOnlyWhenTheUserCanRebuild(): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(false);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_campaign_action', $this->campaign(), 'view');

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherCampaignActions(): array
    {
        return ['edit' => ['edit'], 'clone' => ['clone'], 'new' => ['new']];
    }

    #[DataProvider('otherCampaignActions')]
    public function testNoDetailButtonOnTheOtherCampaignScreens(string $action): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_campaign_action', $this->campaign(), $action);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNoButtonInTheRowMenuWithoutEditAccess(): void
    {
        $campaign = $this->campaign();
        $campaign->method('isPublished')->willReturn(true);
        $this->rebuilder->method('hasEditAccess')->willReturn(false);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', $campaign);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherPlaces(): array
    {
        return [
            'another list'     => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index'],
            'campaign toolbar'  => [ButtonHelper::LOCATION_TOOLBAR_ACTIONS, 'mautic_campaign_index'],
            'campaign index page bar'  => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_campaign_index'],
            'detail row menu'         => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_action'],
            'contact detail page bar' => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_contact_action'],
        ];
    }

    #[DataProvider('otherPlaces')]
    public function testNothingElsewhere(string $location, string $route): void
    {
        $campaign = $this->campaign();
        $campaign->method('isPublished')->willReturn(true);
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $this->rebuilder->method('hasEditAccess')->willReturn(true);
        $event = $this->event($location, $route, $campaign, 'view');

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNothingWhenTheItemIsNotACampaign(): void
    {
        $this->rebuilder->method('canRebuild')->willReturn(true);
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', ['id' => 7]);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }
}
