<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignCanvasZoomSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignCanvasZoomSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'T:'.$key);

        $event = new CustomContentEvent('some_view', $context);
        (new CampaignCanvasZoomSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToTheInjectedContentEvent(): void
    {
        $this->assertSame(
            [CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent'],
            CampaignCanvasZoomSubscriber::getSubscribedEvents()
        );
    }

    public function testTheScriptCarriesTheComponentAndTheFourTitles(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesCanvasZoom', $content);
        $this->assertStringContainsString('"zoomIn":"T:mautic.patches.campaign.zoom_in"', $content);
        $this->assertStringContainsString('"zoomOut":"T:mautic.patches.campaign.zoom_out"', $content);
        $this->assertStringContainsString('"zoomFit":"T:mautic.patches.campaign.zoom_fit"', $content);
        $this->assertStringContainsString('"zoomReset":"T:mautic.patches.campaign.zoom_reset"', $content);
    }

    public function testTheContentIsExactlyTheScriptThenItsInstallCall(): void
    {
        $js     = file_get_contents(__DIR__.'/../../../Resources/js/campaign-canvas-zoom.js');
        $config = json_encode([
            'zoomIn'    => 'T:mautic.patches.campaign.zoom_in',
            'zoomOut'   => 'T:mautic.patches.campaign.zoom_out',
            'zoomFit'   => 'T:mautic.patches.campaign.zoom_fit',
            'zoomReset' => 'T:mautic.patches.campaign.zoom_reset',
        ]);

        $this->assertSame('<script>'.$js.';mauticPatchesCanvasZoomApi.install(Mautic,'.$config.');</script>', $this->content());
    }

    public function testOnlyThePageHeaderGetsTheScript(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }

    public function testTheScriptKeepsJsPlumbInStepWithTheScale(): void
    {
        $script = file_get_contents(__DIR__.'/../../../Resources/js/campaign-canvas-zoom.js');

        $this->assertStringContainsString('setZoom(', $script);
        $this->assertStringContainsString("'0 0'", $script);
    }
}
