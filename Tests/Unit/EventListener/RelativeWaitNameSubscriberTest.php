<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\CampaignEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitNameSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:campaign-wait')]
#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class RelativeWaitNameSubscriberTest extends TestCase
{
    public function testIsSubscribedToPreSaveEvent(): void
    {
        $subscriber = new RelativeWaitNameSubscriber($this->createMock(RelativeWaitNameFormatter::class));
        $events     = $subscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CampaignEvents::CAMPAIGN_PRE_SAVE, $events);
        $this->assertSame(['onPreSave', 0], $events[CampaignEvents::CAMPAIGN_PRE_SAVE]);
    }

    public function testRenamesOnlyWaitRelativeEvents(): void
    {
        $formatter = $this->createMock(RelativeWaitNameFormatter::class);
        $formatter->method('fromProperties')->willReturnCallback(function (?array $properties): string {
            $amount = $properties['amount'] ?? 1;
            $unit   = $properties['unit'] ?? 'd';
            $direction = $properties['direction'] ?? 'after';

            return "Esperar\nEsperar {$amount} {$unit}\nDepois\nCampo";
        });

        $campaign = new Campaign();

        // Create a wait event
        $waitEvent = new Event();
        $waitEvent->setType(RelativeWaitSubscriber::TYPE);
        $waitEvent->setProperties(['amount' => 2, 'unit' => 'd', 'direction' => 'after']);
        $campaign->addEvent(1, $waitEvent);

        // Create a non-wait event
        $emailEvent = new Event();
        $emailEvent->setType('email.send');
        $emailEvent->setName('Send Email');
        $campaign->addEvent(2, $emailEvent);

        $event = new CampaignEvent($campaign);

        $subscriber = new RelativeWaitNameSubscriber($formatter);
        $subscriber->onPreSave($event);

        // The wait event should have its name set (4 lines joined by newlines)
        $lines = explode("\n", $waitEvent->getName());
        $this->assertCount(4, $lines, 'Wait event name should have 4 lines');

        // The email event name should not be changed
        $this->assertSame('Send Email', $emailEvent->getName(), 'Non-wait event name should not be changed');
    }

    public function testHandlesMultipleWaitEvents(): void
    {
        $formatter = $this->createMock(RelativeWaitNameFormatter::class);
        $formatter->method('fromProperties')->willReturnCallback(function (?array $properties): string {
            $amount = $properties['amount'] ?? 1;
            $unit   = $properties['unit'] ?? 'd';

            return "Esperar\nEsperar {$amount} {$unit}\nDepois\nCampo";
        });

        $campaign = new Campaign();

        // Create multiple wait events
        $waitEvent1 = new Event();
        $waitEvent1->setType(RelativeWaitSubscriber::TYPE);
        $waitEvent1->setProperties(['amount' => 2, 'unit' => 'd', 'direction' => 'after']);
        $campaign->addEvent(1, $waitEvent1);

        $waitEvent2 = new Event();
        $waitEvent2->setType(RelativeWaitSubscriber::TYPE);
        $waitEvent2->setProperties(['amount' => 1, 'unit' => 'w', 'direction' => 'after']);
        $campaign->addEvent(2, $waitEvent2);

        $event = new CampaignEvent($campaign);

        $subscriber = new RelativeWaitNameSubscriber($formatter);
        $subscriber->onPreSave($event);

        // Both wait events should have their names set (4 lines)
        $lines1 = explode("\n", $waitEvent1->getName());
        $lines2 = explode("\n", $waitEvent2->getName());
        $this->assertCount(4, $lines1);
        $this->assertCount(4, $lines2);
    }

    public function testHandlesEmptyCampaignEvents(): void
    {
        $formatter = $this->createMock(RelativeWaitNameFormatter::class);
        $campaign  = new Campaign();
        $event     = new CampaignEvent($campaign);

        $subscriber = new RelativeWaitNameSubscriber($formatter);
        // Should not throw an error
        $subscriber->onPreSave($event);

        $this->assertTrue(true);
    }
}
