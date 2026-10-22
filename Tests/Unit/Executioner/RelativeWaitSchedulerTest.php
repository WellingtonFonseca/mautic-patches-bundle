<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Executioner;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\EventCollector\EventCollector;
use Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException;
use Mautic\CampaignBundle\Executioner\Logger\EventLogger;
use Mautic\CampaignBundle\Executioner\Scheduler\Mode\DateTime as DateTimeScheduler;
use Mautic\CampaignBundle\Executioner\Scheduler\Mode\Interval as IntervalScheduler;
use Mautic\CampaignBundle\Executioner\Scheduler\Mode\Optimized as OptimizedScheduler;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Executioner\RelativeWaitDueDate;
use MauticPlugin\MauticPatchesBundle\Executioner\RelativeWaitScheduler;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The scheduler of the campaign (core's mautic.campaign.scheduler, replaced by this subclass) gives each contact the
 * date its relative wait resolved to. Core's modes know one date for the whole batch; this wait has one per contact.
 */
class RelativeWaitSchedulerTest extends TestCase
{
    private \DateTimeImmutable $now;

    /** @var MockObject&RelativeWaitDateResolver */
    private MockObject $resolver;

    /** @var MockObject&RelativeWaitItemSelector */
    private MockObject $selector;

    /** @var LeadEventLog[] */
    private array $logs = [];

    protected function setUp(): void
    {
        $this->now      = new \DateTimeImmutable('2026-03-10 12:00:00', new \DateTimeZone('UTC'));
        $this->resolver = $this->createMock(RelativeWaitDateResolver::class);
        $this->selector = $this->createMock(RelativeWaitItemSelector::class);
        $this->logs     = [];
    }

    private function scheduler(): RelativeWaitScheduler
    {
        $logger = $this->createMock(EventLogger::class);
        $logger->method('buildLogEntry')->willReturnCallback(function (Event $event, ?Lead $contact = null): LeadEventLog {
            $log = new LeadEventLog();
            $log->setEvent($event);
            $log->setLead($contact);

            return $log;
        });
        $logger->method('queueToPersist')->willReturnCallback(function (LeadEventLog $log): void {
            $this->logs[] = $log;
        });
        $logger->method('persistQueuedLogs')->willReturnCallback(fn (): ArrayCollection => new ArrayCollection($this->logs));
        $logger->method('persistCollection')->willReturnSelf();
        $logger->method('clearCollection')->willReturnSelf();

        $interval = $this->createMock(IntervalScheduler::class);
        $interval->method('isContactSpecificExecutionDateRequired')->willReturn(false);

        $collector = $this->createMock(EventCollector::class);
        $collector->method('getEventConfig')->willReturn($this->createMock(AbstractEventAccessor::class));

        $scheduler = new RelativeWaitScheduler(
            new NullLogger(),
            $logger,
            $interval,
            $this->createMock(DateTimeScheduler::class),
            $this->createMock(OptimizedScheduler::class),
            $collector,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CoreParametersHelper::class)
        );
        $scheduler->setRelativeWaitDateResolver($this->resolver);
        $scheduler->setRelativeWaitItemSelector($this->selector);

        return $scheduler;
    }

    private function event(string $type): Event
    {
        $campaign = $this->createMock(Campaign::class);
        $campaign->method('getId')->willReturn(3);
        $event = new Event();
        $event->setType($type);
        $event->setEventType('action');
        $event->setTriggerMode(Event::TRIGGER_MODE_IMMEDIATE);
        $event->setProperties(['dateField' => 'contact:attribution_date', 'amount' => 12, 'unit' => 'd', 'direction' => 'after']);
        $event->setCampaign($campaign);

        return $event;
    }

    private function contact(int $id): Lead
    {
        $contact = $this->createMock(Lead::class);
        $contact->method('getId')->willReturn($id);

        return $contact;
    }

    private function triggerOf(int $contactId): ?string
    {
        foreach ($this->logs as $log) {
            if ($log->getLead()->getId() === $contactId) {
                return $log->getTriggerDate()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }

        return 'no log';
    }

    public function testEachContactIsScheduledForItsOwnDate(): void
    {
        $this->resolver->method('resolve')->willReturnCallback(fn (int $id): ?\DateTimeImmutable => match ($id) {
            1       => new \DateTimeImmutable('2026-03-13 03:00:00', new \DateTimeZone('UTC')),
            2       => new \DateTimeImmutable('2026-03-13 03:00:00', new \DateTimeZone('UTC')),
            default => new \DateTimeImmutable('2026-04-01 10:00:00', new \DateTimeZone('UTC')),
        });

        $this->scheduler()->schedule($this->event(RelativeWaitSubscriber::TYPE), $this->now, new ArrayCollection([1 => $this->contact(1), 2 => $this->contact(2), 3 => $this->contact(3)]));

        $this->assertSame('2026-03-13 03:00:00', $this->triggerOf(1));
        $this->assertSame('2026-03-13 03:00:00', $this->triggerOf(2), 'two students who joined on different days leave together');
        $this->assertSame('2026-04-01 10:00:00', $this->triggerOf(3));
    }

    public function testADatePastOrMissingLeavesOnTheNextRun(): void
    {
        $this->resolver->method('resolve')->willReturnCallback(fn (int $id): ?\DateTimeImmutable => 1 === $id ? new \DateTimeImmutable('2026-03-01 10:00:00', new \DateTimeZone('UTC')) : null);

        $this->scheduler()->schedule($this->event(RelativeWaitSubscriber::TYPE), $this->now, new ArrayCollection([1 => $this->contact(1), 2 => $this->contact(2)]));

        $this->assertSame('2026-03-10 12:00:00', $this->triggerOf(1), 'a date in the past is now, not the past');
        $this->assertSame('2026-03-10 12:00:00', $this->triggerOf(2), 'no date: nothing to wait for');
    }

    public function testTheResolverGetsTheContactAndTheSettingsOfTheEvent(): void
    {
        $this->resolver->expects($this->once())->method('resolve')
            ->with(5, ['dateField' => 'contact:attribution_date', 'amount' => 12, 'unit' => 'd', 'direction' => 'after'], null)
            ->willReturn(null);

        $this->scheduler()->schedule($this->event(RelativeWaitSubscriber::TYPE), $this->now, new ArrayCollection([5 => $this->contact(5)]));
    }

    public function testTheItemsTheCampaignSegmentsSelectAreWhatTheDateIsReadFrom(): void
    {
        $event = $this->event(RelativeWaitSubscriber::TYPE);
        $this->selector->expects($this->once())->method('itemIds')
            ->with($this->callback(static fn ($contact): bool => 5 === $contact->getId()), $event->getCampaign(), $event->getProperties())
            ->willReturn([4, 9]);
        $this->resolver->expects($this->once())->method('resolve')->with(5, $event->getProperties(), [4, 9])->willReturn(null);

        $this->scheduler()->schedule($event, $this->now, new ArrayCollection([5 => $this->contact(5)]));
    }

    public function testOtherEventsAreScheduledForTheDateTheyAreGiven(): void
    {
        $this->resolver->expects($this->never())->method('resolve');
        $date = new \DateTimeImmutable('2026-03-20 08:00:00', new \DateTimeZone('UTC'));

        $this->scheduler()->schedule($this->event('email.send'), $date, new ArrayCollection([1 => $this->contact(1)]));

        $this->assertSame('2026-03-20 08:00:00', $this->triggerOf(1));
    }

    public function testTheRelativeWaitIsAlwaysScheduledEvenThoughItsOwnTimeIsNow(): void
    {
        $scheduler = $this->scheduler();

        $this->assertTrue($scheduler->shouldScheduleEvent($this->event(RelativeWaitSubscriber::TYPE), $this->now, $this->now));
        $this->assertFalse($scheduler->shouldScheduleEvent($this->event('email.send'), $this->now, $this->now), 'core keeps executing an immediate event at once');
    }

    public function testTheFirstEventsOfACampaignGoThroughTheSameScheduling(): void
    {
        $this->resolver->method('resolve')->willReturn(new \DateTimeImmutable('2026-03-13 03:00:00', new \DateTimeZone('UTC')));
        $scheduler = $this->scheduler();

        $scheduler->validateAndScheduleEventForContacts($this->event(RelativeWaitSubscriber::TYPE), $this->now, new ArrayCollection([1 => $this->contact(1)]), $this->now);

        $this->assertSame('2026-03-13 03:00:00', $this->triggerOf(1));

        $this->expectException(NotSchedulableException::class);
        $scheduler->validateAndScheduleEventForContacts($this->event('email.send'), $this->now, new ArrayCollection([1 => $this->contact(1)]), $this->now);
    }

    /**
     * Core's real-time executioner (the children of a decision: "opened the email, wait, send") decides with getExecutionDateTime() and
     * shouldSchedule() alone and, when it schedules, calls scheduleForContact(): the Wait must take that path too.
     */
    public function testAWaitAfterADecisionIsScheduledByTheRealTimePath(): void
    {
        $this->resolver->method('resolve')->willReturn(new \DateTimeImmutable('2026-03-13 03:00:00', new \DateTimeZone('UTC')));
        $scheduler = $this->scheduler();
        $wait      = $this->event(RelativeWaitSubscriber::TYPE);

        $date = $scheduler->getExecutionDateTime($wait, \DateTime::createFromImmutable($this->now));
        $this->assertInstanceOf(RelativeWaitDueDate::class, $date);
        $this->assertSame('2026-03-10 12:00:00', $date->format('Y-m-d H:i:s'), 'the moment itself: nothing is delayed by the mark');
        $this->assertTrue($scheduler->shouldSchedule($date, $this->now), 'a Wait is always scheduled');

        $scheduler->scheduleForContact($wait, $date, $this->contact(1));

        $this->assertSame('2026-03-13 03:00:00', $this->triggerOf(1), 'the contact has its own date, it did not run at once');
    }

    public function testEveryOtherEventKeepsCoresExecutionDateAndRule(): void
    {
        $scheduler = $this->scheduler();
        $date      = $scheduler->getExecutionDateTime($this->event('email.send'), \DateTime::createFromImmutable($this->now));

        $this->assertNotInstanceOf(RelativeWaitDueDate::class, $date);
        $this->assertFalse($scheduler->shouldSchedule($date, $this->now), 'an immediate event runs at once');
        $this->assertTrue($scheduler->shouldSchedule($this->now->modify('+1 hour'), $this->now), 'a future date is still scheduled');
    }

    public function testTheCoreSchedulerIsStillWhatItExtends(): void
    {
        $this->assertInstanceOf(\Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler::class, $this->scheduler());
    }
}
