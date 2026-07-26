<?php declare(strict_types=1);

namespace App\Service\Event;

use App\CronTaskInterface;
use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CronTaskStatus;
use App\Enum\EntityAction;
use App\Enum\EventStatus;
use App\Enum\RealignmentOutcome;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Cms\CmsService;
use App\ValueObject\CronTaskResult;
use App\ValueObject\RealignmentItem;
use App\ValueObject\RealignmentPlan;
use App\ValueObject\RealignmentResult;
use App\ValueObject\ResolvedRecurrence;
use App\ValueObject\ScheduleChange;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use RRule\RRule;
use Symfony\Component\Console\Output\OutputInterface;

readonly class RecurringEventService implements CronTaskInterface
{
    public function __construct(
        private EventRepository $repo,
        private EventSeriesRepository $seriesRepo,
        private EntityManagerInterface $em,
        private EntityActionDispatcher $entityActionDispatcher,
        private CmsBlockRepository $cmsBlockRepository,
        private CmsService $cmsService,
        private RecurrenceResolver $recurrenceResolver,
    ) {}

    public function getIdentifier(): string
    {
        return 'recurring-events';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $count = $this->extentRecurringEvents();

        return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $count . ' events extended');
    }

    public function extentRecurringEvents(): int
    {
        $seriesIds = array_map(static fn(EventSeries $series) => $series->getId(), $this->seriesRepo->findOpen());

        $totalCreated = 0;
        foreach ($seriesIds as $seriesId) {
            $this->em->clear();
            $series = $this->seriesRepo->find($seriesId);
            if ($series !== null) {
                $totalCreated += $this->fillRecurringEvents($series);
            }
        }

        if ($totalCreated > 0) {
            foreach ($this->cmsBlockRepository->findPageIdsWithType(CmsBlockType::EventTeaser) as $pageId) {
                $this->cmsService->invalidatePage($pageId);
            }
        }

        return $totalCreated;
    }

    public function updateRecurringEvents(Event $event, ?DateTimeInterface $syncFrom = null): int
    {
        $series = $event->getSeries();
        if ($series === null) {
            return 0;
        }

        $children = $this->repo->findFollowUpEvents(seriesId: (int) $series->getId(), greaterThan: $syncFrom ?? $event->getStart());

        $updatedCount = 0;
        foreach ($children as $child) {
            if ($child->getId() === $event->getId()) {
                continue; // the series-keyed query can return the anchor itself
            }
            if ($child->getStatus() === EventStatus::Locked) {
                continue; // skip manually-customized events
            }
            $child->setLocation($event->getLocation());
            $child->setPreviewImage($event->getPreviewImage());
            foreach ($event->getTranslation() as $eventTranslation) {
                $childTranslation = $child->findTranslation($eventTranslation->getLanguage());
                if ($childTranslation === null) {
                    $childTranslation = new EventTranslation();
                    $childTranslation->setEvent($child);
                    $childTranslation->setLanguage($eventTranslation->getLanguage());
                    $child->addTranslation($childTranslation);
                }
                $childTranslation->setTitle($eventTranslation->getTitle());
                $childTranslation->setTeaser($eventTranslation->getTeaser());
                $childTranslation->setDescription($eventTranslation->getDescription());

                $this->em->persist($childTranslation);
            }
            $this->em->persist($child);
            ++$updatedCount;
        }
        $this->em->flush();

        return $updatedCount;
    }

    /**
     * Computes how future auto-generated members realign onto a changed schedule:
     * 1. Resolve the anchor's series and the effective rule (the changed rule when given,
     *    otherwise the series rule); without both there is nothing to realign - empty plan.
     *    A change from a rule to NonRecurring closes the series: also an empty plan, the
     *    members keep their dates.
     * 2. Collect members after the OLD start (members sitting between old and new start
     *    must not be orphaned when the anchor moves later), then keep only future ones.
     * 3. Locked and canceled members become skipped items; they neither move nor consume
     *    an occurrence slot.
     * 4. Generate count(realignable)+1 occurrences of the new rule anchored at the new
     *    start (no until-window: existing members keep mapping even beyond the cron
     *    lookahead when the new rule is sparser) and drop the anchor's own date, which
     *    heads the set only when the new start already matches the new rule.
     * 5. Map realignable members in start order 1:1 onto occurrences; the new stop is
     *    start + old duration so cross-midnight events stay intact. Members past the last
     *    occurrence drop out of the plan and keep their dates.
     * 6. A member whose computed dates equal its current ones is DateUnchanged and keeps
     *    its RSVPs; only Moved members lose them on execution.
     */
    public function planRealignment(Event $anchor, ScheduleChange $change): RealignmentPlan
    {
        $series = $anchor->getSeries();
        $seriesClosing = $change->oldRule !== null && $change->newRule === null;
        $rule = $seriesClosing ? null : $change->newRule ?? $series?->getRule();
        $ruleSpec = $seriesClosing ? null : $change->newRuleSpec ?? $series?->getRuleSpec();
        if ($series === null || $rule === null) {
            return new RealignmentPlan(null, (int) $anchor->getId(), null, null, []);
        }

        $now = new DateTimeImmutable();
        $children = [];
        foreach ($this->repo->findFollowUpEvents(seriesId: (int) $series->getId(), greaterThan: $change->oldStart) as $child) {
            if ($child->getId() === $anchor->getId()) {
                continue;
            }
            if (DateTimeImmutable::createFromInterface($child->getStart()) <= $now) {
                continue;
            }
            $children[] = $child;
        }

        if ($children === []) {
            return new RealignmentPlan((int) $series->getId(), (int) $anchor->getId(), $rule, $ruleSpec, []);
        }

        $realignableCount = count(array_filter(
            $children,
            static fn(Event $child): bool => $child->getStatus() !== EventStatus::Locked && !$child->isCanceled(),
        ));

        $occurrences = [];
        $recurrence = $this->recurrenceResolver->resolve($rule, $ruleSpec, $change->newStart);
        if ($realignableCount > 0 && $recurrence !== null) {
            $anchorDate = $change->newStart->format('Y-m-d');
            $rrule = new RRule(
                sprintf('%s;COUNT=%d', $recurrence->rfcString, $realignableCount + 1),
                $anchorDate,
            );
            $occurrences = array_values(array_filter(
                $rrule->getOccurrences(),
                static fn(DateTime $occurrence): bool => $occurrence->format('Y-m-d') !== $anchorDate,
            ));
        }

        $duration = $change->newStop !== null ? $change->newStart->diff($change->newStop) : null;
        $rsvpCounts = $this->repo->getRsvpCounts(array_map(static fn(Event $child): int => (int) $child->getId(), $children));

        $items = [];
        $slot = 0;
        foreach ($children as $child) {
            $currentStart = DateTimeImmutable::createFromInterface($child->getStart());
            $currentStop = $child->getStop() !== null ? DateTimeImmutable::createFromInterface($child->getStop()) : null;
            $rsvpCount = $rsvpCounts[$child->getId()] ?? 0;

            if ($child->getStatus() === EventStatus::Locked) {
                $items[] = new RealignmentItem((int) $child->getId(), $currentStart, $currentStop, null, null, $rsvpCount, RealignmentOutcome::SkippedLocked);
                continue;
            }
            if ($child->isCanceled()) {
                $items[] = new RealignmentItem((int) $child->getId(), $currentStart, $currentStop, null, null, $rsvpCount, RealignmentOutcome::SkippedCanceled);
                continue;
            }

            if (!isset($occurrences[$slot])) {
                break; // the rule ran out of occurrences; remaining members keep their dates
            }

            $occurrence = $occurrences[$slot];
            ++$slot;
            $newStart = $change->newStart->setDate(
                year: (int) $occurrence->format('Y'),
                month: (int) $occurrence->format('m'),
                day: (int) $occurrence->format('d'),
            );
            $newStop = $duration !== null ? $newStart->add($duration) : null;
            $outcome =
                $newStart->getTimestamp() !== $currentStart->getTimestamp() || $newStop?->getTimestamp() !== $currentStop?->getTimestamp()
                    ? RealignmentOutcome::Moved
                    : RealignmentOutcome::DateUnchanged;
            $items[] = new RealignmentItem((int) $child->getId(), $currentStart, $currentStop, $newStart, $newStop, $rsvpCount, $outcome);
        }

        return new RealignmentPlan((int) $series->getId(), (int) $anchor->getId(), $rule, $ruleSpec, $items);
    }

    public function executeRealignment(RealignmentPlan $plan): RealignmentResult
    {
        $removedAttendees = [];
        $movedIds = [];
        foreach ($plan->movedItems() as $item) {
            $event = $this->repo->find($item->eventId);
            if ($event === null || $item->newStart === null) {
                continue;
            }
            $event->setStart(DateTime::createFromImmutable($item->newStart));
            $event->setStop($item->newStop !== null ? DateTime::createFromImmutable($item->newStop) : null);
            foreach ($event->getRsvp()->toArray() as $attendee) {
                $userId = (int) $attendee->getId();
                $removedAttendees[$userId] ??= ['user' => $attendee, 'dates' => []];
                $removedAttendees[$userId]['dates'][] = $item->currentStart;
                $event->removeRsvp($attendee);
            }
            $event->setRsvpNotificationSentAt(null);
            $event->setEventReminderSentAt(null);
            $this->em->persist($event);
            $movedIds[] = $item->eventId;
        }
        $this->em->flush();

        foreach ($movedIds as $movedId) {
            $this->entityActionDispatcher->dispatch(EntityAction::UpdateEvent, $movedId);
        }

        if ($movedIds !== []) {
            foreach ($this->cmsBlockRepository->findPageIdsWithType(CmsBlockType::EventTeaser) as $pageId) {
                $this->cmsService->invalidatePage($pageId);
            }
        }

        return new RealignmentResult(count($movedIds), $removedAttendees);
    }

    private function fillRecurringEvents(EventSeries $series): int
    {
        $template = $this->repo->findNewestSeriesMember((int) $series->getId());
        if ($template === null) {
            return 0; // a series without a non-locked member cannot be extended
        }

        $recurrence = $this->recurrenceResolver->resolve($series->getRule(), $series->getRuleSpec(), $template->getStart());
        if ($recurrence === null) {
            return 0;
        }

        $rrule = $this->createRRule($template, $recurrence);
        $createdEvents = [];
        $now = new DateTime();
        $anchorDate = $template->getStart()->format('Y-m-d');

        foreach ($rrule as $occurrence) {
            // Match by date, not position: an off-pattern anchor does not head the set.
            if ($occurrence->format('Y-m-d') === $anchorDate) {
                continue;
            }
            if ($occurrence < $now) {
                continue;
            }

            $newEvent = $this->createRecurringEvent($series, $template, $occurrence);
            $this->em->persist($newEvent);
            $createdEvents[] = $newEvent;
        }

        $this->em->flush();

        // Dispatch CreateEvent for each created recurring event (now they have IDs)
        foreach ($createdEvents as $createdEvent) {
            $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, (int) $createdEvent->getId());
        }

        return count($createdEvents);
    }

    private function createRRule(Event $templateEvent, ResolvedRecurrence $recurrence): RRule
    {
        $until = (new DateTime())->modify($recurrence->lookaheadModifier);

        return new RRule(
            sprintf('%s;UNTIL=%s', $recurrence->rfcString, $until->format('Ymd')),
            $templateEvent->getStart()->format('Y-m-d'),
        );
    }

    private function createRecurringEvent(EventSeries $series, Event $template, DateTime $occurrence): Event
    {
        $recurringEvent = new Event();
        $recurringEvent->setUser($template->getUser());
        $recurringEvent->setStatus(EventStatus::Published);
        $recurringEvent->setFeatured(false);
        $recurringEvent->setLocation($template->getLocation());
        $recurringEvent->setPreviewImage($template->getPreviewImage());
        $recurringEvent->setInitial(false);
        $recurringEvent->setStart($this->updateDate($template->getStart(), $occurrence));
        $recurringEvent->setStop($this->updateDate($template->getStop(), $occurrence));
        $recurringEvent->setSeries($series);
        $recurringEvent->setCreatedAt(new DateTimeImmutable());

        // Reload host collection from managed template to avoid detached state issues
        if ($template->getHost()->count() > 0) {
            foreach ($template->getHost() as $host) {
                $recurringEvent->addHost($host);
            }
        }

        $recurringEvent->setType($template->getType());

        foreach ($template->getTranslation() as $eventTranslation) {
            $newEventTranslation = new EventTranslation();
            $newEventTranslation->setEvent($recurringEvent);
            $newEventTranslation->setLanguage($eventTranslation->getLanguage());
            $newEventTranslation->setTitle($eventTranslation->getTitle());
            $newEventTranslation->setTeaser($eventTranslation->getTeaser());
            $newEventTranslation->setDescription($eventTranslation->getDescription());

            $this->em->persist($newEventTranslation);
            $recurringEvent->addTranslation($newEventTranslation);
        }

        return $recurringEvent;
    }

    private function updateDate(?DateTimeInterface $target, DateTime $occurrence): ?DateTime
    {
        if (!$target instanceof DateTimeInterface) {
            return null;
        }

        $newDate = DateTime::createFromInterface($target);
        $newDate->setDate(year: (int) $occurrence->format('Y'), month: (int) $occurrence->format('m'), day: (int) $occurrence->format('d'));

        return $newDate;
    }
}
