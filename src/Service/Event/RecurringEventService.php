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
use App\Repository\RsvpGuestRepository;
use App\Service\Cms\CmsService;
use App\ValueObject\CronTaskResult;
use App\ValueObject\RealignmentItem;
use App\ValueObject\RealignmentPlan;
use App\ValueObject\RealignmentResult;
use App\ValueObject\ScheduleChange;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class RecurringEventService implements CronTaskInterface
{
    public function __construct(
        private EventRepository $repo,
        private EventSeriesRepository $seriesRepo,
        private RsvpGuestRepository $rsvpGuestRepo,
        private EntityManagerInterface $em,
        private EntityActionDispatcher $entityActionDispatcher,
        private CmsBlockRepository $cmsBlockRepository,
        private CmsService $cmsService,
        private RecurrenceResolver $recurrenceResolver,
        private OccurrenceCalculator $calculator,
        private ClockInterface $clock,
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
                continue;
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

    public function planRealignment(Event $anchor, ScheduleChange $change): RealignmentPlan
    {
        $series = $anchor->getSeries();
        $seriesClosing = $change->oldRule !== null && $change->newRule === null;
        $rule = $seriesClosing ? null : $change->newRule ?? $series?->getRule();
        $ruleSpec = $seriesClosing ? null : $change->newRuleSpec ?? $series?->getRuleSpec();
        if ($series === null || $rule === null) {
            return new RealignmentPlan(null, (int) $anchor->getId(), null, null, []);
        }

        $now = $this->clock->now();
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
        $pattern = $this->recurrenceResolver->resolve($rule, $ruleSpec, $change->newStart);
        if ($realignableCount > 0 && $pattern !== null) {
            $occurrences = $this->calculator->take($pattern, $change->newStart, $realignableCount);
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
                $this->rsvpGuestRepo->deleteFor($event, $attendee);
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

        $pattern = $this->recurrenceResolver->resolve($series->getRule(), $series->getRuleSpec(), $template->getStart());
        if ($pattern === null) {
            return 0;
        }

        $now = $this->clock->now();
        $createdEvents = [];

        foreach ($this->calculator->until($pattern, $template->getStart(), $now->modify($pattern->period->lookaheadModifier())) as $occurrence) {
            // Compare the composed start, not the bare date: an occurrence today may still be ahead.
            $start = $this->updateDate($template->getStart(), $occurrence);
            if (!$start instanceof DateTime || $start < $now) {
                continue;
            }

            $newEvent = $this->createRecurringEvent($series, $template, $start, $this->updateDate($template->getStop(), $occurrence));
            $this->em->persist($newEvent);
            $createdEvents[] = $newEvent;
        }

        $this->em->flush();

        foreach ($createdEvents as $createdEvent) {
            $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, (int) $createdEvent->getId());
        }

        return count($createdEvents);
    }

    private function createRecurringEvent(EventSeries $series, Event $template, DateTime $start, ?DateTime $stop): Event
    {
        $recurringEvent = new Event();
        $recurringEvent->setUser($template->getUser());
        $recurringEvent->setStatus(EventStatus::Published);
        $recurringEvent->setFeatured(false);
        $recurringEvent->setLocation($template->getLocation());
        $recurringEvent->setPreviewImage($template->getPreviewImage());
        $recurringEvent->setInitial(false);
        $recurringEvent->setStart($start);
        $recurringEvent->setStop($stop);
        $recurringEvent->setSeries($series);
        $recurringEvent->setCreatedAt(new DateTimeImmutable());

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

    private function updateDate(?DateTimeInterface $target, DateTimeImmutable $occurrence): ?DateTime
    {
        if (!$target instanceof DateTimeInterface) {
            return null;
        }

        $newDate = DateTime::createFromInterface($target);
        $newDate->setDate(year: (int) $occurrence->format('Y'), month: (int) $occurrence->format('m'), day: (int) $occurrence->format('d'));

        return $newDate;
    }
}
