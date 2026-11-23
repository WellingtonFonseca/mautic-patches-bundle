<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignConnectionTrashSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:campaign')]
#[Group('risk:UI')]
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

    public function testThePreviewIsLockedAndItsCirclesAreShownAgain(): void
    {
        $script = file_get_contents(__DIR__.'/../../../Resources/js/campaign-connection-trash.js');

        // no unlink on the Preview: lines not detachable, circles disabled, detach and drop refused
        $this->assertStringContainsString('lockPreview', $script);
        $this->assertStringContainsString('setDetachable(false)', $script);
        $this->assertStringContainsString("bind('beforeDetach'", $script);
        $this->assertStringContainsString("bind('beforeDrop'", $script);
        // the connector circles core hides there are back, and only there
        $this->assertStringContainsString('#preview-container .jtk-endpoint svg{display:block!important}', $script);
    }
}
