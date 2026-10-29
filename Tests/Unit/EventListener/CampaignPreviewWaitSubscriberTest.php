<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Twig\Helper\DateHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignPreviewWaitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignPreviewWaitSubscriberTest extends TestCase
{
    private function subscriber(): CampaignPreviewWaitSubscriber
    {
        // Echoes key and arguments, so the test sees exactly what core's keys and arguments are.
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key, array $params = []): string => $key.($params ? ' '.json_encode($params) : '')
        );
        // final class: only the date branch needs it, and that one is checked live (scripts/5/e2e).
        $dateHelper = (new \ReflectionClass(DateHelper::class))->newInstanceWithoutConstructor();

        return new CampaignPreviewWaitSubscriber($translator, $dateHelper);
    }

    private function event(int $id, string $mode, int $interval = 0, ?string $unit = null, ?string $path = null): Event
    {
        $event = new Event();
        (new \ReflectionProperty(Event::class, 'id'))->setValue($event, $id);
        $event->setTriggerMode($mode);
        $event->setTriggerInterval($interval);
        $event->setTriggerIntervalUnit($unit);
        $event->setDecisionPath($path);

        return $event;
    }

    private function content(string $context, array $events, bool $withCampaign = true): string
    {
        $campaign = new Campaign();
        foreach ($events as $event) {
            $campaign->addEvent($event->getId(), $event);
        }

        $customContent = new CustomContentEvent('some_view', $context, $withCampaign ? ['campaign' => $campaign] : []);
        $this->subscriber()->injectViewCustomContent($customContent);

        return implode('', $customContent->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, CampaignPreviewWaitSubscriber::getSubscribedEvents());
    }

    public function testTheTextOfAnIntervalIsCoresOwn(): void
    {
        $label = $this->subscriber()->label($this->event(1, Event::TRIGGER_MODE_INTERVAL, 2, 'd'));

        $this->assertStringStartsWith('mautic.campaign.connection.trigger.interval.label {', $label);
        $this->assertStringContainsString('"%number%":2', $label);
        $this->assertStringContainsString('mautic.campaign.event.intervalunit.d {\\"%count%\\":2}', $label);
    }

    public function testTheNoPathUsesTheInactionText(): void
    {
        $label = $this->subscriber()->label($this->event(1, Event::TRIGGER_MODE_INTERVAL, 1, 'i', Event::PATH_INACTION));

        $this->assertStringStartsWith('mautic.campaign.connection.trigger.interval.label_inaction ', $label);
    }

    public function testTheNoPathOfAConditionWaitsLikeAnyEvent(): void
    {
        $condition = new Event();
        $condition->setEventType(Event::TYPE_CONDITION);
        $child = $this->event(1, Event::TRIGGER_MODE_INTERVAL, 1, 'd', Event::PATH_INACTION);
        $child->setParent($condition);

        $label = $this->subscriber()->label($child);

        $this->assertStringStartsWith('mautic.campaign.connection.trigger.interval.label ', $label);
    }

    public function testTheNoPathOfADecisionKeepsTheInactionText(): void
    {
        $decision = new Event();
        $decision->setEventType(Event::TYPE_DECISION);
        $child = $this->event(1, Event::TRIGGER_MODE_INTERVAL, 1, 'd', Event::PATH_INACTION);
        $child->setParent($decision);

        $this->assertStringStartsWith('mautic.campaign.connection.trigger.interval.label_inaction ', $this->subscriber()->label($child));
    }

    public function testAnImmediateEventHasNoText(): void
    {
        $this->assertSame('', $this->subscriber()->label($this->event(1, Event::TRIGGER_MODE_IMMEDIATE)));
    }

    public function testAScriptWithOneTextPerDelayedNodeOnTheCampaignPageTabsContent(): void
    {
        $js = $this->content('tabs.content', [
            $this->event(171, Event::TRIGGER_MODE_IMMEDIATE),
            $this->event(172, Event::TRIGGER_MODE_INTERVAL, 1, 'i'),
        ]);

        $this->assertStringStartsWith('<script>', $js);
        $this->assertStringEndsWith('</script>', $js);
        $this->assertStringContainsString('"CampaignEvent_172":', $js);
        $this->assertStringNotContainsString('CampaignEvent_171', $js);
        $this->assertStringContainsString('window.mauticPatchesPreviewWaits={"CampaignEvent_172":', $js);
        $this->assertStringContainsString('window.mauticPatchesApplyWaits()', $js);
    }

    public function testWithoutADelayedEventTheTextsAreEmptyToReplaceTheCampaignSeenBefore(): void
    {
        $this->assertStringContainsString('window.mauticPatchesPreviewWaits={};', $this->content('tabs.content', [$this->event(1, Event::TRIGGER_MODE_IMMEDIATE)]));
    }

    public function testNothingOnAnotherContextOrWithoutACampaign(): void
    {
        $this->assertSame('', $this->content('something.else', [$this->event(1, Event::TRIGGER_MODE_INTERVAL, 1, 'd')]));
        $this->assertSame('', $this->content('tabs.content', [], false));
    }

    public function testTheTextCannotCloseTheScriptTag(): void
    {
        $js = CampaignPreviewWaitSubscriber::script(['CampaignEvent_1' => '</script><b>']);

        $this->assertStringNotContainsString('</script>', $js);
    }

    public function testTheEscapingKeepsTheTextReadableAndSafe(): void
    {
        $js = CampaignPreviewWaitSubscriber::script(['CampaignEvent_1' => 'esperar até <b>R&D</b> às 10h'], ['CampaignEvent_1' => ['triggerHour' => '10:00']]);

        // JSON_UNESCAPED_UNICODE: the accents stay readable; JSON_HEX_TAG and JSON_HEX_AMP: no < or & in the page script
        $slash = chr(92);
        $this->assertStringContainsString('esperar até '.$slash.'u003Cb'.$slash.'u003ER'.$slash.'u0026D'.$slash.'u003C'.$slash.'/b'.$slash.'u003E às 10h', $js);
        $this->assertStringNotContainsString('<b>', $js);
        $this->assertStringNotContainsString('R&D', $js);
    }

    private function intervalEvent(?string $hour, ?string $start, ?string $stop, array $days, string $mode = Event::TRIGGER_MODE_INTERVAL): Event
    {
        $event = $this->event(7, $mode, 1, 'd');
        $event->setTriggerHour($hour);
        $event->setTriggerRestrictedStartHour($start);
        $event->setTriggerRestrictedStopHour($stop);
        $event->setTriggerRestrictedDaysOfWeek($days);

        return $event;
    }

    public function testTheScheduleIsTheFieldsOfTheEventInTheShapeTheBuilderHolds(): void
    {
        $schedule = CampaignPreviewWaitSubscriber::schedule($this->intervalEvent('09:30', '10:00', '16:00', [5 => 1, 3 => 3]));

        $this->assertSame([
            'triggerMode'                 => 'interval',
            'triggerHour'                 => '09:30',
            'triggerRestrictedStartHour'  => '10:00',
            'triggerRestrictedStopHour'   => '16:00',
            'triggerRestrictedDaysOfWeek' => [1, 3],
        ], $schedule);
    }

    public function testAnyOneOfTheFieldsIsASchedule(): void
    {
        $this->assertSame('09:30', CampaignPreviewWaitSubscriber::schedule($this->intervalEvent('09:30', null, null, []))['triggerHour']);
        $this->assertSame('10:00', CampaignPreviewWaitSubscriber::schedule($this->intervalEvent(null, '10:00', null, []))['triggerRestrictedStartHour']);
        $this->assertSame('16:00', CampaignPreviewWaitSubscriber::schedule($this->intervalEvent(null, null, '16:00', []))['triggerRestrictedStopHour']);
        $this->assertSame([-1], CampaignPreviewWaitSubscriber::schedule($this->intervalEvent(null, null, null, [-1]))['triggerRestrictedDaysOfWeek']);
        $this->assertNull(CampaignPreviewWaitSubscriber::schedule($this->intervalEvent(null, null, null, [])));
    }

    public function testAnImmediateEventHasNoSchedule(): void
    {
        $this->assertNull(CampaignPreviewWaitSubscriber::schedule($this->intervalEvent('09:30', '10:00', '16:00', [1], Event::TRIGGER_MODE_IMMEDIATE)));
    }

    public function testAWaitUntilADateCarriesItsDateWithItsOffsetNotTheHours(): void
    {
        $event = $this->intervalEvent('09:30', '10:00', '16:00', [1], Event::TRIGGER_MODE_DATE);
        $event->setTriggerDate(new \DateTime('2026-10-31 17:00:00', new \DateTimeZone('UTC')));

        $this->assertSame(['triggerMode' => 'date', 'triggerDate' => '2026-10-31T17:00:00+00:00'], CampaignPreviewWaitSubscriber::schedule($event));

        $event->setTriggerDate(new \DateTime('2026-10-31 14:00:00', new \DateTimeZone('America/Sao_Paulo')));
        $this->assertSame('2026-10-31T14:00:00-03:00', CampaignPreviewWaitSubscriber::schedule($event)['triggerDate'], 'the offset is the date\'s own, so the instant is right');
    }

    public function testAWaitUntilADateWithoutADateHasNoSchedule(): void
    {
        $this->assertNull(CampaignPreviewWaitSubscriber::schedule($this->intervalEvent(null, null, null, [], Event::TRIGGER_MODE_DATE)));
    }

    public function testTheScriptCarriesTheScheduleOfTheNodeThatHasOne(): void
    {
        $campaign = new Campaign();
        $campaign->addEvent(7, $this->intervalEvent(null, '10:00', '16:00', [1, 2, 3]));
        $campaign->addEvent(8, $this->event(8, Event::TRIGGER_MODE_INTERVAL, 1, 'd'));
        $customContent = new CustomContentEvent('some_view', 'tabs.content', ['campaign' => $campaign]);
        $this->subscriber()->injectViewCustomContent($customContent);
        $js = implode('', $customContent->getContent());

        $this->assertStringContainsString('window.mauticPatchesPreviewSchedules={"CampaignEvent_7":{"triggerMode":"interval","triggerHour":null,"triggerRestrictedStartHour":"10:00","triggerRestrictedStopHour":"16:00","triggerRestrictedDaysOfWeek":[1,2,3]}};', $js);
        $this->assertStringNotContainsString('"CampaignEvent_8":{"trigger', $js, 'a wait without a schedule has no extra lines');
        $this->assertStringContainsString('"CampaignEvent_8":"mautic.campaign', $js, 'but it keeps its wait text');
        $this->assertLessThan(strpos($js, 'window.mauticPatchesApplyWaits()'), strpos($js, 'window.mauticPatchesPreviewSchedules'), 'the schedules are on the page before the nodes are drawn');
    }

    public function testAnEventWithoutAWaitTextHasNoScheduleEither(): void
    {
        $js = $this->content('tabs.content', [$this->intervalEvent('09:30', null, null, [], Event::TRIGGER_MODE_IMMEDIATE)]);

        $this->assertStringContainsString('window.mauticPatchesPreviewSchedules={};', $js);
    }

    public function testWithoutAScheduleTheSchedulesAreEmptyToReplaceTheCampaignSeenBefore(): void
    {
        $this->assertStringContainsString('window.mauticPatchesPreviewSchedules={};', $this->content('tabs.content', [$this->event(1, Event::TRIGGER_MODE_INTERVAL, 1, 'd')]));
        $this->assertStringContainsString('window.mauticPatchesPreviewSchedules={};', CampaignPreviewWaitSubscriber::script([]));
    }
}
