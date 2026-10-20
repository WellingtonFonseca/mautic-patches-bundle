<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactFieldsConditionSubscriber;
use MauticPlugin\MauticPatchesBundle\Form\Type\ContactFieldsConditionType;
use MauticPlugin\MauticPatchesBundle\Service\ContactFields\ContactFieldsMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactFieldsConditionSubscriberTest extends TestCase
{
    private function lead(int $id): Lead
    {
        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn($id);

        return $lead;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function executionEvent(?Lead $lead, array $properties, string $type = 'lead.field_values'): CampaignExecutionEvent
    {
        return new CampaignExecutionEvent(
            ['lead' => $lead, 'event' => ['type' => $type, 'properties' => $properties], 'eventDetails' => null, 'systemTriggered' => true, 'eventSettings' => []],
            false
        );
    }

    public function testTheConditionIsRegisteredWithItsFormAndItsOwnEvent(): void
    {
        $event = new CampaignBuilderEvent($this->createMock(TranslatorInterface::class));
        (new ContactFieldsConditionSubscriber($this->createMock(ContactFieldsMatcher::class)))->onCampaignBuild($event);

        $condition = $event->getConditions()['lead.field_values'] ?? null;
        $this->assertNotNull($condition);
        $this->assertSame(ContactFieldsConditionType::class, $condition['formType']);
        $this->assertSame(ContactFieldsConditionSubscriber::ON_TRIGGER_CONDITION, $condition['eventName']);
        $this->assertSame('@MauticPatches/Campaign/contact_fields_condition_widget.html.twig', $condition['formTheme']);
        $this->assertArrayNotHasKey('lead.field_value', $event->getConditions(), 'core\'s condition is not touched');
    }

    public function testTheFirstCriterionAndTheAdditionalOnesAreAskedTogether(): void
    {
        $matcher = $this->createMock(ContactFieldsMatcher::class);
        $matcher->expects($this->once())->method('matches')->with(7, [
            ['field' => 'email', 'operator' => 'in', 'value' => 'a@x.com|b@x.com'],
            ['field' => 'points', 'operator' => 'gte', 'value' => '1'],
        ])->willReturn(true);

        $event = $this->executionEvent($this->lead(7), [
            'field' => 'email', 'operator' => 'in', 'value' => 'a@x.com|b@x.com',
            'additionalCriteria' => [['field' => 'points', 'operator' => 'gte', 'value' => '1']],
        ]);
        (new ContactFieldsConditionSubscriber($matcher))->onTrigger($event);

        $this->assertTrue($event->getResult());
    }

    public function testAConditionThatDoesNotMatchIsFalse(): void
    {
        $matcher = $this->createMock(ContactFieldsMatcher::class);
        $matcher->method('matches')->willReturn(false);

        $event = $this->executionEvent($this->lead(7), ['field' => 'email', 'operator' => '=', 'value' => 'x']);
        (new ContactFieldsConditionSubscriber($matcher))->onTrigger($event);

        $this->assertFalse($event->getResult());
    }

    public function testAnotherEventTypeIsLeftAlone(): void
    {
        $matcher = $this->createMock(ContactFieldsMatcher::class);
        $matcher->expects($this->never())->method('matches');

        $event = $this->executionEvent($this->lead(7), [], 'lead.field_value');
        (new ContactFieldsConditionSubscriber($matcher))->onTrigger($event);

        $this->assertFalse((bool) $event->getResult());
    }

    public function testAContactWithoutAnIdIsFalse(): void
    {
        $matcher = $this->createMock(ContactFieldsMatcher::class);
        $matcher->expects($this->never())->method('matches');

        $event = $this->executionEvent($this->lead(0), ['field' => 'email', 'operator' => '=', 'value' => 'x']);
        (new ContactFieldsConditionSubscriber($matcher))->onTrigger($event);

        $this->assertFalse($event->getResult());
    }
}
