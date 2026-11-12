<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignActionsTabSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CampaignActionsTabSubscriberTest extends TestCase
{
    private const VIEW = '@MauticCampaign/Campaign/details.html.twig';

    /** @var array<string, list<array<string, mixed>>> */
    private array $events = [
        'action'   => [['id' => 3, 'name' => 'Enviar HSM'], ['id' => 5, 'name' => 'Esperar']],
        'decision' => [['id' => 4, 'name' => 'Abriu o email']],
    ];

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame('inject', CampaignActionsTabSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_CONTENT]);
    }

    public function testTheTabLinkGoesInTheTabsOfTheCampaignPage(): void
    {
        $event = new CustomContentEvent(self::VIEW, 'tabs', ['events' => $this->events]);

        (new CampaignActionsTabSubscriber())->inject($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates, 'the link only, never the pane in the list of tabs');
        $this->assertSame('@MauticPatches/Campaign/actions_tab_link.html.twig', $templates[0]['template']);
    }

    public function testThePaneListsOnlyTheActionStepsInTheirOrder(): void
    {
        $event = new CustomContentEvent(self::VIEW, 'tabs.content', ['events' => $this->events]);

        (new CampaignActionsTabSubscriber())->inject($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('@MauticPatches/Campaign/actions_tab_pane.html.twig', $templates[0]['template']);
        $this->assertSame($this->events['action'], $templates[0]['vars']['actions']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function ignoredProvider(): array
    {
        return [
            'another view'         => ['@MauticEmail/Email/details.html.twig', 'tabs', ['events' => ['action' => [['id' => 1]]]]],
            'another context'      => [self::VIEW, 'details.stats.graph.below', ['events' => ['action' => [['id' => 1]]]]],
            'a campaign with no action' => [self::VIEW, 'tabs', ['events' => ['action' => [], 'decision' => [['id' => 1]]]]],
            'no events at all'     => [self::VIEW, 'tabs.content', []],
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    #[DataProvider('ignoredProvider')]
    public function testAddsNothingElsewhere(string $view, string $context, array $vars): void
    {
        $event = new CustomContentEvent($view, $context, $vars);

        (new CampaignActionsTabSubscriber())->inject($event);

        $this->assertSame([], $event->getTemplates());
    }
}
