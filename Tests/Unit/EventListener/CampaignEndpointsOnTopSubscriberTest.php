<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignEndpointsOnTopSubscriber;
use PHPUnit\Framework\TestCase;

class CampaignEndpointsOnTopSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignEndpointsOnTopSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheCirclesGoAboveTheNodesOfTheBuilder(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<style>', $content);
        // core: node z-index 1010, circle 1005; the trash of the lines is 1100
        $this->assertMatchesRegularExpression('/#CampaignCanvas \.jtk-endpoint\{z-index:(\d+)!important\}/', $content);
        // the mouse over a circle is a crosshair, also over its svg
        $this->assertStringContainsString('#CampaignCanvas .jtk-endpoint,#CampaignCanvas .jtk-endpoint *{cursor:crosshair}', $content);
        preg_match('/z-index:(\d+)/', $content, $m);
        $this->assertGreaterThan(1010, (int) $m[1]);
        $this->assertLessThan(1100, (int) $m[1]);
    }

    public function testTheScriptThatMovesTheCirclesOutIsOnThePage(): void
    {
        $content = $this->content();

        $this->assertStringContainsString('mauticPatchesEndpointsOutside', $content);
        $this->assertStringContainsString('campaignEndpointDefinitions', $content);
    }

    public function testOnlyThePageHeaderGetsTheStyle(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }
}
