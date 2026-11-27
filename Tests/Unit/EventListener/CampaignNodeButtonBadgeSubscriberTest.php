<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignNodeButtonBadgeSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:campaign')]
#[Group('risk:UI')]
class CampaignNodeButtonBadgeSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => '['.$id.']');
        $event = new CustomContentEvent('some_view', $context);
        (new CampaignNodeButtonBadgeSubscriber($translator))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignNodeButtonBadgeSubscriber::getSubscribedEvents());
    }

    public function testInjectsOnPageHeaderLeftOnly(): void
    {
        $this->assertStringStartsWith('<script>', $this->content());
        $this->assertStringEndsWith('</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }

    public function testPassesTheFourTranslatedLabelsAndRunsOnce(): void
    {
        $js = $this->content();
        foreach (['clone', 'edit', 'remove', 'back'] as $kind) {
            $this->assertStringContainsString('"'.$kind.'":"[mautic.patches.campaign.node.'.$kind.']"', $js);
        }
        $this->assertStringContainsString('if(!window.mauticPatchesNodeButtonBadges){mauticPatchesNodeButtonBadgesApi.install(window,', $js);
    }

    public function testTranslationsExistInBothLanguages(): void
    {
        foreach (['en_US', 'pt_BR'] as $lang) {
            $ini = file_get_contents(__DIR__.'/../../../Translations/'.$lang.'/messages.ini');
            foreach (['clone', 'edit', 'remove', 'back'] as $kind) {
                $this->assertMatchesRegularExpression('/^mautic\.patches\.campaign\.node\.'.$kind.'="[^"]+"/m', $ini, $lang.' '.$kind);
            }
        }
    }
}
