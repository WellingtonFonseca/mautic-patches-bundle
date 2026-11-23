<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignListClickSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class CampaignListClickSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('mautic_patches_campaign_details', ['id' => 0])->willReturn('/s/campaign-details/0');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => "[{$key}]");

        $event = new CustomContentEvent('some_view', $context);
        (new CampaignListClickSubscriber($router, $translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignListClickSubscriber::getSubscribedEvents());
    }

    public function testInjectsAScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testTheScriptKnowsTheModalRouteAndTheTitle(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('"/s/campaign-details/"+m[1]', $js);
        $this->assertStringContainsString('"[mautic.patches.campaign.details.title]"', $js);
        $this->assertStringContainsString('"#MauticSharedModal"', $js);
        $this->assertStringContainsString('Mautic.ajaxifyModal(el,event);', $js);
    }

    public function testItRunsBeforeCoresAjaxLinkHandlerAndOnlyInTheCampaignList(): void
    {
        $js = $this->content();

        $this->assertStringContainsString('},true);', $js, 'capture phase');
        $this->assertStringContainsString('"#page-list-wrapper a[href]"', $js);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $js);
        $this->assertStringContainsString('/^\/s\/campaigns\/view\/(\d+)', $js);
        $this->assertStringContainsString('.page-list-actions', $js, 'the row menu keeps going to the page');
        $this->assertStringContainsString('event.ctrlKey||event.metaKey||event.shiftKey', $js);
    }

    public function testTheScriptIsInstalledOnlyOnce(): void
    {
        $this->assertStringContainsString('if(window.mauticPatchesCampaignListClicks){return;}', $this->content());
    }
}
