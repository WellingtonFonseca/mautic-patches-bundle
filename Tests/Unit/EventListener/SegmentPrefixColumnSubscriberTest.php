<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentPrefixColumnSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentPrefixColumnSubscriberTest extends TestCase
{
    /**
     * @param array<string, string> $session the list's order in the session
     */
    private function subscriber(?string $prefix = 'Compras', array $session = []): SegmentPrefixColumnSubscriber
    {
        $stack   = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        foreach ($session as $key => $value) {
            $request->getSession()->set($key, $value);
        }
        $stack->push($request);

        $store = $this->createStub(SegmentPrefixStore::class);
        $store->method('get')->willReturn($prefix);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $params = []) => 'T:'.$key);

        return new SegmentPrefixColumnSubscriber($store, $translator, $stack);
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentPrefixColumnSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeft(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);

        $js = implode('', $event->getContent());
        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"T:mautic.patches.segment.prefix"', $js);
        $this->assertStringContainsString('"T:mautic.core.sort.ascending.description"', $js);
        $this->assertStringContainsString('"T:mautic.core.sort.descending.description"', $js);
        $this->assertStringContainsString("Mautic.reorderTableData('lead.list','segment_prefix','list','.page-list')", $js);
        $this->assertStringContainsString('.text(prefix)', $js);
        $this->assertStringContainsString('$head.before(', $js);
    }

    public function testEachRowGetsAMarkerTemplateWithItsPrefix(): void
    {
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => new LeadList()]);
        $this->subscriber('Vendas')->injectViewCustomContent($event);

        $this->assertSame(
            [['template' => '@MauticPatches/Segment/prefix_marker.html.twig', 'vars' => ['prefix' => 'Vendas', 'sort' => 'none']]],
            $event->getTemplates()
        );
    }

    #[DataProvider('sortStates')]
    public function testTheMarkerCarriesTheSortStateOfTheListsSessionOrder(array $session, string $expected): void
    {
        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => new LeadList()]);
        $this->subscriber('Vendas', $session)->injectViewCustomContent($event);

        $this->assertSame($expected, $event->getTemplates()[0]['vars']['sort']);
    }

    public static function sortStates(): array
    {
        return [
            'sorted by another column' => [['mautic.lead.list.orderby' => 'l.name', 'mautic.lead.list.orderbydir' => 'DESC'], 'none'],
            'ascending'                => [['mautic.lead.list.orderby' => 'segment_prefix', 'mautic.lead.list.orderbydir' => 'ASC'], 'ascending'],
            'descending'               => [['mautic.lead.list.orderby' => 'segment_prefix', 'mautic.lead.list.orderbydir' => 'DESC'], 'descending'],
            'no direction yet'         => [['mautic.lead.list.orderby' => 'segment_prefix'], 'ascending'],
        ];
    }

    public function testOtherContextsAndNonSegmentsAreIgnored(): void
    {
        $event = new CustomContentEvent('some_view', 'something.else', ['item' => new LeadList()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
        $this->assertSame([], $event->getContent());

        $event = new CustomContentEvent('some_view', 'segment.name', ['item' => new \stdClass()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
    }
}
