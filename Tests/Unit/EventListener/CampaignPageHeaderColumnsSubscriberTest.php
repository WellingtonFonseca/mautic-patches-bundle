<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPageHeaderColumnsSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('area:campaign')]
#[Group('risk:UI')]
class CampaignPageHeaderColumnsSubscriberTest extends TestCase
{
    private function content(string $route, string $context = 'page.header.left'): string
    {
        $stack = new RequestStack();
        $stack->push(new Request([], [], ['_route' => $route]));
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

    public function testOtherRoutesAndContextsAreUntouched(): void
    {
        $this->assertSame('', $this->content('mautic_contact_action'));
        $this->assertSame('', $this->content('mautic_campaign_action', 'somewhere.else'));
    }
}
