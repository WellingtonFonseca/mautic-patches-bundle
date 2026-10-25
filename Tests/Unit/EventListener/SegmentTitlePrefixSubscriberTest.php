<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentTitlePrefixSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;

class SegmentTitlePrefixSubscriberTest extends TestCase
{
    private function segment(string $name = 'Onboarding', int $id = 7): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        (new \ReflectionProperty($segment, 'id'))->setValue($segment, $id);

        return $segment;
    }

    private function content(?string $prefix, array $vars, string $context = 'page.header.left'): string
    {
        $store = $this->createStub(SegmentPrefixStore::class);
        $store->method('get')->willReturn($prefix);
        $event = new CustomContentEvent('some_view', $context, $vars);
        (new SegmentTitlePrefixSubscriber($store))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentTitlePrefixSubscriber::getSubscribedEvents());
    }

    public function testTheHeadingAndTheTabGetThePrefix(): void
    {
        $js = $this->content('Compras', ['list' => $this->segment()]);

        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"Onboarding"', $js);
        $this->assertStringContainsString('"Compras - Onboarding"', $js);
        $this->assertStringContainsString('h1.page-header-title', $js);
    }

    public function testNothingWithoutAPrefixOrASegmentOrOnAnotherContext(): void
    {
        $this->assertSame('', $this->content(null, ['list' => $this->segment()]));
        $this->assertSame('', $this->content('', ['list' => $this->segment()]));
        $this->assertSame('', $this->content('Compras', []));
        $this->assertSame('', $this->content('Compras', ['list' => 'not a segment']));
        $this->assertSame('', $this->content('Compras', ['list' => $this->segment()], 'something.else'));
        // a segment that is not saved yet has no prefix to look up
        $this->assertSame('', $this->content('Compras', ['list' => new LeadList()]));
    }

    public function testATitleWithQuotesOrTagsCannotBreakOutOfTheScript(): void
    {
        $js = $this->content('A', ['list' => $this->segment('</script><b>"x"')]);

        $this->assertStringNotContainsString('</script><b>', $js);
        $this->assertSame(1, substr_count($js, '</script>'));
    }
}
