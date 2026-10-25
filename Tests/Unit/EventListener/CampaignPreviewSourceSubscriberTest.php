<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPreviewSourceSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;

class CampaignPreviewSourceSubscriberTest extends TestCase
{
    /**
     * @param array<int, string> $prefixes segment id => prefix
     * @param array<int, string> $segments segment id => name, in the order of the campaign's lists
     */
    private function content(string $context, array $prefixes, array $segments, bool $withCampaign = true): string
    {
        $campaign = new Campaign();
        foreach ($segments as $id => $name) {
            $segment = new LeadList();
            $segment->setName($name);
            (new \ReflectionProperty(LeadList::class, 'id'))->setValue($segment, $id);
            $campaign->addList($segment);
        }

        $store = $this->createStub(SegmentPrefixStore::class);
        $store->method('all')->willReturn($prefixes);

        $event = new CustomContentEvent('some_view', $context, $withCampaign ? ['campaign' => $campaign] : []);
        (new CampaignPreviewSourceSubscriber($store))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignPreviewSourceSubscriber::getSubscribedEvents());
    }

    public function testTheScriptHasTheOldAndTheNewNamesInTheOrderOfTheLists(): void
    {
        $js = $this->content('tabs.content', [5 => 'Compras'], [5 => 'Onboarding', 6 => 'Livre']);

        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"Onboarding, Livre"', $js);
        $this->assertStringContainsString('"Compras - Onboarding, Livre"', $js);
    }

    public function testOtherContextsGetNothing(): void
    {
        $this->assertSame('', $this->content('left.section.top', [5 => 'Compras'], [5 => 'Onboarding']));
    }

    public function testAPageWithoutACampaignGetsNothing(): void
    {
        $this->assertSame('', $this->content('tabs.content', [5 => 'Compras'], [5 => 'Onboarding'], false));
    }

    public function testNothingToChangeGetsNothing(): void
    {
        $this->assertSame('', $this->content('tabs.content', [], [5 => 'Onboarding']));
        $this->assertSame('', $this->content('tabs.content', [5 => 'Compras'], []));
    }
}
