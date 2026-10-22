<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Executioner;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitDateResolver;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitItemSelector;

/**
 * Core's campaign scheduler (service mautic.campaign.scheduler, whose class RelativeWaitPass replaces by this one) with the
 * campaign wait "Wait" (patches.wait_relative): its date is one PER CONTACT, and core's modes (immediate, interval, date) give
 * one date for the whole batch and have no point to extend (a fixed `switch` on the trigger mode).
 *
 * The wait is stored with the immediate mode, so core would run it at once and never call schedule(). Three places are changed,
 * only for this action; every other event goes through core's code:
 *   - shouldScheduleEvent(): always true, so the executioners call schedule() (the way core handles contact specific dates);
 *   - validateAndScheduleEventForContacts(): the first events of a campaign (KickoffExecutioner) schedule it instead of throwing
 *     NotSchedulableException (which means "run it now");
 *   - schedule(): each contact is scheduled for the date RelativeWaitDateResolver gives it. A date in the past, or none (the
 *     contact has no such date), is "now": the log is scheduled for the moment and the next run of the campaign executes it.
 * When the date comes, core runs the logs whose trigger date has passed; the stored mode is immediate, so nothing recalculates it.
 */
class RelativeWaitScheduler extends EventScheduler
{
    private ?RelativeWaitDateResolver $dateResolver = null;

    private ?RelativeWaitItemSelector $itemSelector = null;

    /** Set by the container (RelativeWaitPass): the constructor is core's. */
    public function setRelativeWaitDateResolver(RelativeWaitDateResolver $dateResolver): void
    {
        $this->dateResolver = $dateResolver;
    }

    /** The items of a Custom Object the campaign's segments select, the ones a date of that object is read from. */
    public function setRelativeWaitItemSelector(RelativeWaitItemSelector $itemSelector): void
    {
        $this->itemSelector = $itemSelector;
    }

    private function isRelativeWait(Event $event): bool
    {
        return RelativeWaitSubscriber::TYPE === $event->getType();
    }

    public function shouldScheduleEvent(Event $event, \DateTimeInterface $executionDate, \DateTimeInterface $now): bool
    {
        return $this->isRelativeWait($event) || parent::shouldScheduleEvent($event, $executionDate, $now);
    }

    public function validateAndScheduleEventForContacts(Event $event, \DateTimeInterface $executionDateTime, ArrayCollection $contacts, \DateTimeInterface $comparedFromDateTime): void
    {
        if ($this->isRelativeWait($event)) {
            $this->schedule($event, $executionDateTime, $contacts);

            return;
        }

        parent::validateAndScheduleEventForContacts($event, $executionDateTime, $contacts, $comparedFromDateTime);
    }

    /**
     * @param bool $isInactiveEvent
     */
    public function schedule(Event $event, \DateTimeInterface $executionDate, ArrayCollection $contacts, $isInactiveEvent = false): void
    {
        if (!$this->isRelativeWait($event) || null === $this->dateResolver) {
            parent::schedule($event, $executionDate, $contacts, $isInactiveEvent);

            return;
        }

        // The contacts that leave at the same moment are scheduled together, as core does with a batch.
        $now     = \DateTimeImmutable::createFromInterface($executionDate);
        $groups  = [];
        $dates   = [];
        foreach ($contacts as $contact) {
            $config                     = (array) $event->getProperties();
            $itemIds                    = $this->itemSelector?->itemIds($contact, $event->getCampaign(), $config);
            $date                       = $this->dateResolver->resolve((int) $contact->getId(), $config, $itemIds);
            $date                       = null !== $date && $date > $now ? $date : $now;
            $dates[$date->getTimestamp()] = $date;
            $groups[$date->getTimestamp()][$contact->getId()] = $contact;
        }

        foreach ($groups as $timestamp => $group) {
            parent::schedule($event, $dates[$timestamp], new ArrayCollection($group), $isInactiveEvent);
        }
    }
}
