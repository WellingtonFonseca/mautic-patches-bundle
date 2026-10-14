<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPrefixColumnSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignPrefixColumnSubscriberTest extends TestCase
{
    /**
     * @param array<string, string> $session the list's order in the session
     */
    private function subscriber(?string $prefix = 'Compras', array $session = []): CampaignPrefixColumnSubscriber
    {
        $stack   = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        foreach ($session as $key => $value) {
            $request->getSession()->set($key, $value);
        }
        $stack->push($request);

        $store = $this->createMock(CampaignPrefixStore::class);
        $store->method('get')->willReturn($prefix);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $params = []) => 'T:'.$key);

        return new CampaignPrefixColumnSubscriber($store, $translator, $stack);
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignPrefixColumnSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheScriptOnPageHeaderLeft(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);

        $js = implode('', $event->getContent());
        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringContainsString('"T:mautic.patches.campaign.prefix"', $js);
        $this->assertStringContainsString('"T:mautic.core.sort.ascending.description"', $js);
        $this->assertStringContainsString('"T:mautic.core.sort.descending.description"', $js);
        $this->assertStringContainsString("Mautic.reorderTableData('campaign','campaign_prefix','list','.page-list')", $js);
        $this->assertStringContainsString('.text(prefix)', $js);
        $this->assertStringContainsString('$head.before(', $js);
    }

    public function testEachRowGetsAMarkerTemplateWithItsPrefix(): void
    {
        $event = new CustomContentEvent('some_view', 'campaign.name', ['item' => new Campaign()]);
        $this->subscriber('Vendas')->injectViewCustomContent($event);

        $this->assertSame(
            [['template' => '@MauticPatches/Campaign/prefix_marker.html.twig', 'vars' => ['prefix' => 'Vendas', 'sort' => 'none']]],
            $event->getTemplates()
        );
    }

    #[DataProvider('sortStates')]
    public function testTheMarkerCarriesTheSortStateOfTheListsSessionOrder(array $session, string $expected): void
    {
        $event = new CustomContentEvent('some_view', 'campaign.name', ['item' => new Campaign()]);
        $this->subscriber('Vendas', $session)->injectViewCustomContent($event);

        $this->assertSame($expected, $event->getTemplates()[0]['vars']['sort']);
    }

    public static function sortStates(): array
    {
        return [
            'sorted by another column' => [['mautic.campaign.orderby' => 'l.name', 'mautic.campaign.orderbydir' => 'DESC'], 'none'],
            'ascending'                => [['mautic.campaign.orderby' => 'campaign_prefix', 'mautic.campaign.orderbydir' => 'ASC'], 'ascending'],
            'descending'               => [['mautic.campaign.orderby' => 'campaign_prefix', 'mautic.campaign.orderbydir' => 'DESC'], 'descending'],
            'no direction yet'         => [['mautic.campaign.orderby' => 'campaign_prefix'], 'ascending'],
        ];
    }

    public function testOtherContextsAndNonCampaignsAreIgnored(): void
    {
        $event = new CustomContentEvent('some_view', 'something.else', ['item' => new Campaign()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
        $this->assertSame([], $event->getContent());

        $event = new CustomContentEvent('some_view', 'campaign.name', ['item' => new \stdClass()]);
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertSame([], $event->getTemplates());
    }
}
