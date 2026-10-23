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
 * The wait is stored with the immediate mode, so core would run it at once and never call schedule(). Five places are changed,
 * only for this action; every other event goes through core's code:
 *   - getExecutionDateTime() + shouldSchedule(): its execution date is marked (RelativeWaitDueDate) and a marked date is always
 *     scheduled: core's real-time executioner (the children of a decision) decides with those two alone;
 *   - shouldScheduleEvent(): always true, so the other executioners call schedule() (the way core handles contact specific dates);
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

    /**
     * The execution date of a Wait is the mark RelativeWaitDueDate (the moment itself): every executioner then goes through the scheduling,
     * the real-time one (the children of a decision) included.
     */
    public function getExecutionDateTime(Event $event, ?\DateTimeInterface $compareFromDateTime = null, ?\DateTime $comparedToDateTime = null): \DateTimeInterface
    {
        $date = parent::getExecutionDateTime($event, $compareFromDateTime, $comparedToDateTime);

        return RelativeWaitSubscriber::isWait($event) ? RelativeWaitDueDate::createFromInterface($date) : $date;
    }

    public function shouldSchedule(\DateTimeInterface $executionDate, \DateTimeInterface $now): bool
    {
        return $executionDate instanceof RelativeWaitDueDate || parent::shouldSchedule($executionDate, $now);
    }

    public function shouldScheduleEvent(Event $event, \DateTimeInterface $executionDate, \DateTimeInterface $now): bool
    {
        return RelativeWaitSubscriber::isWait($event) || parent::shouldScheduleEvent($event, $executionDate, $now);
    }

    public function validateAndScheduleEventForContacts(Event $event, \DateTimeInterface $executionDateTime, ArrayCollection $contacts, \DateTimeInterface $comparedFromDateTime): void
    {
        if (RelativeWaitSubscriber::isWait($event)) {
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
        if (!RelativeWaitSubscriber::isWait($event) || null === $this->dateResolver) {
            parent::schedule($event, $executionDate, $contacts, $isInactiveEvent);

            return;
        }

        // The contacts that leave at the same moment are scheduled together, as core does with a batch. A date in the past, or none, is the
        // execution date itself ("now").
        $floor    = \DateTimeImmutable::createFromInterface($executionDate);
        $config   = (array) $event->getProperties();
        $campaign = $event->getCampaign();
        $groups   = [];
        foreach ($contacts as $contact) {
            $date = $this->dateResolver->resolve((int) $contact->getId(), $config, $this->itemSelector?->itemIds($contact, $campaign, $config));
            $date = null !== $date && $date > $floor ? $date : $floor;

            $groups[$date->getTimestamp()] ??= ['date' => $date, 'contacts' => []];
            $groups[$date->getTimestamp()]['contacts'][$contact->getId()] = $contact;
        }

        foreach ($groups as $group) {
            parent::schedule($event, $group['date'], new ArrayCollection($group['contacts']), $isInactiveEvent);
        }
    }
}
