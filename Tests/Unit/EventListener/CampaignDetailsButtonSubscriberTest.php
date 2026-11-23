<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignDetailsButtonSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class CampaignDetailsButtonSubscriberTest extends TestCase
{
    private CampaignDetailsButtonSubscriber $subscriber;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $route, array $p) => "/s/campaigns/{$p['objectAction']}/{$p['objectId']}");
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $this->subscriber = new CampaignDetailsButtonSubscriber($router, $translator);
    }

    private function event(string $location, string $route, mixed $item): CustomButtonEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new CustomButtonEvent($location, $request, [], $item);
    }

    private function campaign(): Campaign
    {
        $campaign = $this->createMock(Campaign::class);
        $campaign->method('getId')->willReturn(7);

        return $campaign;
    }

    public function testSubscribesToTheButtonEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, CampaignDetailsButtonSubscriber::getSubscribedEvents());
    }

    public function testAddsDetailsToTheRowMenuOfTheCampaignListFirst(): void
    {
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', $this->campaign());

        $this->subscriber->injectViewButtons($event);

        $buttons = array_values($event->getButtons());
        $this->assertCount(1, $buttons);
        $this->assertSame('[mautic.core.details]', $buttons[0]['btnText']);
        $this->assertSame('/s/campaigns/view/7', $buttons[0]['attr']['href']);
        $this->assertSame('ajax', $buttons[0]['attr']['data-toggle']);
        $this->assertSame('ri-information-line', $buttons[0]['iconClass']);
        $this->assertGreaterThan(150, $buttons[0]['priority'], 'above Edit');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherPlaces(): array
    {
        return [
            'another list'     => [ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_contact_index'],
            'campaign page'    => [ButtonHelper::LOCATION_PAGE_ACTIONS, 'mautic_campaign_action'],
            'campaign toolbar' => [ButtonHelper::LOCATION_TOOLBAR_ACTIONS, 'mautic_campaign_index'],
        ];
    }

    #[DataProvider('otherPlaces')]
    public function testNothingElsewhere(string $location, string $route): void
    {
        $event = $this->event($location, $route, $this->campaign());

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }

    public function testNothingWhenTheItemIsNotACampaign(): void
    {
        $event = $this->event(ButtonHelper::LOCATION_LIST_ACTIONS, 'mautic_campaign_index', ['id' => 7]);

        $this->subscriber->injectViewButtons($event);

        $this->assertSame([], $event->getButtons());
    }
}
