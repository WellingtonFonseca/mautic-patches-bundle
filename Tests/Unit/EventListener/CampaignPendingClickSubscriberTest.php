<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPendingClickSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignPendingClickSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('mautic_patches_campaign_event_pending', ['eventId' => 0])->willReturn('/s/campaign-pending/0');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $event = new CustomContentEvent('some_view', $context);
        (new CampaignPendingClickSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignPendingClickSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringContainsString('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheHourglassLooksClickable(): void
    {
        $this->assertStringContainsString('cursor:pointer', $this->content());
    }

    public function testTheScriptKnowsTheModalRouteAndTheTitle(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('"/s/campaign-pending/"+id', $js);
        $this->assertStringContainsString('"[mautic.patches.campaign.pending.title]"', $js);
        $this->assertStringContainsString('Mautic.ajaxifyModal(el,event);', $js);
    }

    public function testItRunsBeforeCoresHandlersAndOnlyOnTheHourglass(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('},true);', $js, 'capture phase');
        $this->assertStringContainsString('".campaign-event-stat > span[data-toggle]"', $js);
        $this->assertStringContainsString('".ri-hourglass-line"', $js);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $js);
    }
}
