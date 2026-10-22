<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use MauticPlugin\MauticPatchesBundle\Form\Type\RelativeWaitType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The campaign action "Wait" (patches.wait_relative): holds the contact until a date counted from a date field of the contact or
 * of a Custom Object item ("12 days after the discipline starts"), the same date for everyone who has it, whenever they joined the
 * campaign. It does nothing when its date comes: the events after it run then. The date is given to each contact by
 * RelativeWaitScheduler (core's modes have one date per batch); this class only registers the action and answers its execution.
 */
class RelativeWaitSubscriber implements EventSubscriberInterface
{
    public const TYPE       = 'patches.wait_relative';
    public const ON_TRIGGER = 'mautic.patches.on_campaign_trigger_wait_relative';

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            self::ON_TRIGGER                  => ['onTrigger', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction(self::TYPE, [
            'label'           => 'mautic.patches.wait_relative',
            'description'     => 'mautic.patches.wait_relative.descr',
            'formType'        => RelativeWaitType::class,
            'eventName'       => self::ON_TRIGGER,
            'hideTriggerMode' => true,
        ]);
    }

    public function onTrigger(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::TYPE)) {
            return;
        }

        $event->setResult(true);
    }
}
