<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignConnectionTrashSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignConnectionTrashSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('mautic.patches.campaign.disconnect')->willReturn('Desconectar');

        $event = new CustomContentEvent('some_view', $context);
        (new CampaignConnectionTrashSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testTheScriptCarriesTheComponentAndTheTranslatedTitle(): void
    {
        $content = $this->content();

        $this->assertStringStartsWith('<script>', $content);
        $this->assertStringContainsString('mauticPatchesConnectionTrash', $content);
        $this->assertStringContainsString('deleteConnection', $content);
        $this->assertStringContainsString('"title":"Desconectar"', $content);
    }

    public function testOnlyThePageHeaderGetsTheScript(): void
    {
        $this->assertSame('', $this->content('somewhere.else'));
    }

    public function testTheButtonIsOnlyOnTheCircleAndSitsAboveIt(): void
    {
        $script = file_get_contents(__DIR__.'/../../../Resources/js/campaign-connection-trash.js');

        // no button on the lines: no overlay, no hover area made on the path
        $this->assertStringNotContainsString('addOverlay', $script);
        $this->assertStringNotContainsString('mautic-patches-trash-hit', $script);
        // the circles are at 1011 (CampaignEndpointsOnTopSubscriber)
        preg_match('/z-index:(\d+)/', $script, $m);
        $this->assertGreaterThan(1011, (int) $m[1]);
    }
}
