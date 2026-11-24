<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPageHeaderColumnsSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('area:campaign')]
#[Group('risk:UI')]
class CampaignPageHeaderColumnsSubscriberTest extends TestCase
{
    private function content(string $route, string $context = 'page.header.left', ?string $objectAction = null): string
    {
        $stack = new RequestStack();
        $attributes = ['_route' => $route];
        if (null !== $objectAction) {
            $attributes['objectAction'] = $objectAction;
        }
        $stack->push(new Request([], [], $attributes));
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignPageHeaderColumnsSubscriber($stack))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testCampaignRouteGetsTheNewColumns(): void
    {
        $content = $this->content('mautic_campaign_action');

        $this->assertStringContainsString('col-xs-12 col-sm-8 col-md-8 va-m', $content);
        $this->assertStringContainsString('col-xs-12 col-sm-4 col-md-4 va-m', $content);
    }

    public function testSegmentRouteGetsTheNewColumns(): void
    {
        $content = $this->content('mautic_segment_index');

        $this->assertStringContainsString('col-xs-12 col-sm-8 col-md-8 va-m', $content);
        $this->assertStringContainsString('col-xs-12 col-sm-4 col-md-4 va-m', $content);
    }

    public function testScriptSevenFiveUsesTheColumnClassesAndTheOnceGuard(): void
    {
        $script = CampaignPageHeaderColumnsSubscriber::script(7, 5);

        $this->assertStringContainsString('title.className="col-xs-12 col-sm-7 col-md-7 va-m"', $script);
        $this->assertStringContainsString('toolbar.className="col-xs-12 col-sm-5 col-md-5 va-m"', $script);
        $this->assertStringContainsString('!header.hasAttribute("data-patches-cols")', $script);
        $this->assertStringContainsString('header.setAttribute("data-patches-cols","1")', $script);
        $this->assertStringNotContainsString('col-md-8', $script);
    }

    public function testScriptDefaultsStayEightFour(): void
    {
        $script = CampaignPageHeaderColumnsSubscriber::script();

        $this->assertStringContainsString('col-sm-8 col-md-8 va-m', $script);
        $this->assertStringContainsString('col-sm-4 col-md-4 va-m', $script);
    }

    #[DataProvider('detailPages')]
    public function testIsDetailPage(string $route, string $objectAction, bool $expected): void
    {
        $this->assertSame($expected, CampaignPageHeaderColumnsSubscriber::isDetailPage($route, $objectAction));
    }

    public static function detailPages(): iterable
    {
        yield 'custom object view' => ['mautic_custom_object_view', '', true];
        yield 'custom item view' => ['mautic_custom_item_view', '', true];
        foreach (['campaign', 'segment', 'contact', 'email'] as $name) {
            yield $name.' view' => ['mautic_'.$name.'_action', 'view', true];
            yield $name.' edit' => ['mautic_'.$name.'_action', 'edit', false];
            yield $name.' no action' => ['mautic_'.$name.'_action', '', false];
        }
        yield 'campaign list' => ['mautic_campaign_index', '', false];
        yield 'segment list' => ['mautic_segment_index', '', false];
        yield 'contact list' => ['mautic_contact_index', '', false];
        yield 'email list' => ['mautic_email_index', '', false];
        yield 'custom object list' => ['mautic_custom_object_list', '', false];
        yield 'custom item list' => ['mautic_custom_item_list', '', false];
        yield 'unrelated view' => ['mautic_user_action', 'view', false];
    }

    #[DataProvider('detailRoutes')]
    public function testDetailRoutesGetSevenFive(string $route, ?string $action): void
    {
        $content = $this->content($route, 'page.header.left', $action);

        $this->assertStringContainsString('col-xs-12 col-sm-7 col-md-7 va-m', $content);
        $this->assertStringContainsString('col-xs-12 col-sm-5 col-md-5 va-m', $content);
        $this->assertStringNotContainsString('col-md-8', $content);
    }

    public static function detailRoutes(): iterable
    {
        yield ['mautic_custom_object_view', null];
        yield ['mautic_custom_item_view', null];
        yield ['mautic_campaign_action', 'view'];
        yield ['mautic_segment_action', 'view'];
        yield ['mautic_contact_action', 'view'];
        yield ['mautic_email_action', 'view'];
    }

    public function testCampaignEditKeepsEightFour(): void
    {
        $content = $this->content('mautic_campaign_action', 'page.header.left', 'edit');

        $this->assertStringContainsString('col-sm-8 col-md-8 va-m', $content);
        $this->assertStringNotContainsString('col-md-7 va-m"', $content);
    }

    public function testOtherRoutesAndContextsAreUntouched(): void
    {
        $this->assertSame('', $this->content('mautic_contact_action'));
        $this->assertSame('', $this->content('mautic_campaign_action', 'somewhere.else'));
    }
}
