<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Form\Type\RelativeWaitType;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RelativeWaitSubscriberTest extends TestCase
{
    private function executionEvent(string $type): CampaignExecutionEvent
    {
        $lead = $this->createStub(Lead::class);
        $lead->method('getId')->willReturn(1);

        return new CampaignExecutionEvent(
            ['lead' => $lead, 'event' => ['type' => $type, 'properties' => []], 'eventDetails' => null, 'systemTriggered' => true, 'eventSettings' => []],
            false
        );
    }

    public function testTheWaitIsAnActionOfTheCampaignBuilderWithItsOwnForm(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $event = new CampaignBuilderEvent($translator);
        (new RelativeWaitSubscriber())->onCampaignBuild($event);

        $action = $event->getActions()['patches.wait_relative'] ?? null;
        $this->assertNotNull($action);
        $this->assertSame(RelativeWaitType::class, $action['formType']);
        $this->assertSame('mautic.patches.wait_relative', $action['label']);
        $this->assertSame(RelativeWaitSubscriber::ON_TRIGGER, $action['eventName']);
        $this->assertTrue($action['hideTriggerMode'], 'its own settings are the timing: core\'s immediate / interval / date does not apply');
    }

    public function testWhenItsDateComesTheActionDoesNothingAndSucceeds(): void
    {
        $event = $this->executionEvent('patches.wait_relative');
        (new RelativeWaitSubscriber())->onTrigger($event);

        $this->assertTrue($event->getResult(), 'the next event of the campaign runs');
    }

    public function testAnotherActionIsLeftAlone(): void
    {
        $event = $this->executionEvent('email.send');
        (new RelativeWaitSubscriber())->onTrigger($event);

        $this->assertFalse($event->getResult());
    }
}
