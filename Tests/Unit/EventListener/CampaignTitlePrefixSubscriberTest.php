<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignTitlePrefixSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use PHPUnit\Framework\TestCase;

class CampaignTitlePrefixSubscriberTest extends TestCase
{
    private function content(string $context, ?string $prefix, bool $withCampaign = true, string $name = 'Onboarding'): string
    {
        $campaign = new Campaign();
        $campaign->setName($name);
        (new \ReflectionProperty(Campaign::class, 'id'))->setValue($campaign, 7);

        $store = $this->createMock(CampaignPrefixStore::class);
        $store->method('get')->with(7)->willReturn($prefix);

        $event = new CustomContentEvent('some_view', $context, $withCampaign ? ['campaign' => $campaign] : []);
        (new CampaignTitlePrefixSubscriber($store))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignTitlePrefixSubscriber::getSubscribedEvents());
    }

    public function testTheScriptHasTheOldAndTheNewTitle(): void
    {
        $js = $this->content('page.header.left', 'Compras');

        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"Onboarding"', $js);
        $this->assertStringContainsString('"Compras - Onboarding"', $js);
    }

    public function testOtherContextsGetNothing(): void
    {
        $this->assertSame('', $this->content('tabs.content', 'Compras'));
    }

    public function testAPageWithoutACampaignGetsNothing(): void
    {
        $this->assertSame('', $this->content('page.header.left', 'Compras', false));
    }

    public function testACampaignWithoutPrefixGetsNothing(): void
    {
        $this->assertSame('', $this->content('page.header.left', null));
        $this->assertSame('', $this->content('page.header.left', ''));
    }

    public function testTheNameIsEscapedInTheScript(): void
    {
        $js = $this->content('page.header.left', 'Compras', true, 'A "quote" </script>');

        $this->assertStringNotContainsString('</script>A', $js);
        $this->assertSame(1, substr_count($js, '</script>'));
    }
}
