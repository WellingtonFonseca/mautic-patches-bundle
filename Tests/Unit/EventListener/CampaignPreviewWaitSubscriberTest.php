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
}
