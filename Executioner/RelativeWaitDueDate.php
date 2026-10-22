<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Executioner;

/**
 * The execution date core's executioners get for a Wait node (RelativeWaitScheduler::getExecutionDateTime): the moment itself, because the real
 * date is one PER CONTACT and only schedule() knows it. It only marks the date, so shouldSchedule() answers "schedule it" even though it equals now.
 * Core's real-time executioner (the children of a decision: "opened the email, wait, send") decides with getExecutionDateTime() and
 * shouldSchedule() alone, it never asks shouldScheduleEvent(), so without the mark the Wait would run at once there.
 */
final class RelativeWaitDueDate extends \DateTimeImmutable
{
}
