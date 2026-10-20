<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use MauticPlugin\MauticPatchesBundle\Form\Type\ContactFieldsConditionType;
use MauticPlugin\MauticPatchesBundle\Service\ContactFields\ContactFieldsMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The campaign condition "Contact fields" (lead.field_values): several criteria on fields of the contact or its company,
 * ALL of them must hold. A new condition instead of a change to core's "Contact field value" (lead.field_value), which keeps
 * working as it is: core evaluates that one in a fixed branch with no point to extend, and one criterion only.
 *
 * Stored properties: field, operator, value (the first criterion, as the other conditions) and additionalCriteria.
 */
class ContactFieldsConditionSubscriber implements EventSubscriberInterface
{
    public const TYPE                 = 'lead.field_values';
    public const ON_TRIGGER_CONDITION = 'mautic.patches.on_campaign_trigger_contact_fields';

    public function __construct(private ContactFieldsMatcher $matcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            self::ON_TRIGGER_CONDITION        => ['onTrigger', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addCondition(self::TYPE, [
            'label'       => 'mautic.patches.condition.contact_fields',
            'description' => 'mautic.patches.condition.contact_fields.descr',
            'formType'    => ContactFieldsConditionType::class,
            'formTheme'   => '@MauticPatches/Campaign/contact_fields_condition_widget.html.twig',
            'eventName'   => self::ON_TRIGGER_CONDITION,
        ]);
    }

    public function onTrigger(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::TYPE)) {
            return;
        }

        $contact = $event->getLead();
        if (empty($contact) || !$contact->getId()) {
            $event->setResult(false);

            return;
        }

        $config   = $event->getConfig();
        $criteria = array_merge(
            [['field' => (string) ($config['field'] ?? ''), 'operator' => (string) ($config['operator'] ?? ''), 'value' => $config['value'] ?? '']],
            array_values((array) ($config['additionalCriteria'] ?? []))
        );

        $event->setResult($this->matcher->matches((int) $contact->getId(), $criteria));
    }
}
