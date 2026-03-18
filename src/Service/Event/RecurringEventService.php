<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Entity\Event;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Repository\EventRepository;
use App\Service\Cms\CmsPageCacheService;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use RRule\RRule;

readonly class RecurringEventService
{
    public function __construct(
        private EventRepository $repo,
        private EntityManagerInterface $em,
        private EntityActionDispatcher $entityActionDispatcher,
        private CmsPageCacheService $cmsPageCacheService,
    ) {}

    public function extentRecurringEvents(): void
    {
        $eventIds = array_map(static fn(Event $e) => $e->getId(), $this->repo->findAllRecurring());

        $totalCreated = 0;
        foreach ($eventIds as $eventId) {
            $this->em->clear();
            $event = $this->repo->find($eventId);
            if ($event !== null) {
                $totalCreated += $this->fillRecurringEvents($event);
            }
        }

        if ($totalCreated > 0) {
            foreach ($this->cmsPageCacheService->findEventTeaserPageIds() as $pageId) {
                $this->cmsPageCacheService->invalidatePage($pageId);
            }
        }
    }

    public function updateRecurringEvents(Event $event): int
    {
        if ($event->getRecurringRule() instanceof EventInterval) {
            // is recurring, must be the parent
            $parent = clone $event;
        } elseif ($event->getRecurringOf() !== null) {
            // has parent, load parent
            $parent = $this->repo->findOneBy(['id' => $event->getRecurringOf()]);
            if ($parent === null) {
                // Parent event was deleted, cannot update recurring events
                return 0;
            }
        } else { // no parent, no recurring, nothing to do
            return 0;
        }

        $children = $this->repo->findFollowUpEvents(parentEventId: $parent->getId(), greaterThan: $event->getStart());

        $updatedCount = 0;
        foreach ($children as $child) {
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

    private function fillRecurringEvents(Event $event): int
    {
        if (!$event->getRecurringRule() instanceof EventInterval) {
            return 0;
        }

        $rrule = $this->createRRule($event, $event->getRecurringRule());
        $createdEvents = [];

        $skipFirst = true;
        foreach ($rrule as $occurrence) {
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }
            if ($occurrence < new DateTime()) {
                continue;
            }

            $newEvent = $this->createRecurringEvent($event, $occurrence);
            $this->em->persist($newEvent);
            $createdEvents[] = $newEvent;
        }

        $this->em->flush();

        // Dispatch CreateEvent for each created recurring event (now they have IDs)
        foreach ($createdEvents as $createdEvent) {
            $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, $createdEvent->getId());
        }

        return count($createdEvents);
    }

    private function createRRule(Event $event, EventInterval $recurringRule): RRule
    {
        $today = new DateTime();
        $ruleInterval = EventInterval::BiMonthly === $recurringRule ? 2 : 1;
        $ruleFrequency = match ($recurringRule) {
            EventInterval::Daily => RRule::DAILY,
            EventInterval::Weekly, EventInterval::BiMonthly => RRule::WEEKLY,
            EventInterval::Monthly => RRule::MONTHLY,
            EventInterval::Yearly => RRule::YEARLY,
        };

        return new RRule([
            'freq' => $ruleFrequency,
            'interval' => $ruleInterval,
            'dtstart' => $this->getLastRecurringEventDate($event),
            'until' => (match ($recurringRule) {
                EventInterval::Daily => (clone $today)->modify('+2 weeks'),
                EventInterval::Weekly => (clone $today)->modify('+3 months'),
                EventInterval::BiMonthly => (clone $today)->modify('+6 months'),
                EventInterval::Monthly => (clone $today)->modify('+6 months'),
                EventInterval::Yearly => (clone $today)->modify('+3 years'),
            })->format('Y-m-d'),
        ]);
    }

    private function createRecurringEvent(Event $parent, DateTime $occurrence): Event
    {
        $recurringEvent = new Event();
        $recurringEvent->setUser($parent->getUser());
        $recurringEvent->setStatus(EventStatus::Published);
        $recurringEvent->setFeatured(false);
        $recurringEvent->setLocation($parent->getLocation());
        $recurringEvent->setPreviewImage($parent->getPreviewImage());
        $recurringEvent->setInitial(false);
        $recurringEvent->setStart($this->updateDate($parent->getStart(), $occurrence));
        $recurringEvent->setStop($this->updateDate($parent->getStop(), $occurrence));
        $recurringEvent->setRecurringOf($parent->getId());
        $recurringEvent->setRecurringRule(null);
        $recurringEvent->setCreatedAt(new DateTimeImmutable());

        // Reload host collection from managed parent to avoid detached state issues
        if ($parent->getHost()->count() > 0) {
            foreach ($parent->getHost() as $host) {
                $recurringEvent->addHost($host);
            }
        }

        $recurringEvent->setType($parent->getType());

        foreach ($parent->getTranslation() as $eventTranslation) {
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
        $newDate->setDate(
            year: (int) $occurrence->format('Y'),
            month: (int) $occurrence->format('m'),
            day: (int) $occurrence->format('d'),
        );

        return $newDate;
    }

    private function getLastRecurringEventDate(Event $event): string
    {
        $lastRecurringEvent = $this->repo->findOneBy(['recurringOf' => $event->getId()], ['start' => 'DESC']);
        if ($lastRecurringEvent === null) {
            $lastRecurringEvent = $event;
        }

        return $lastRecurringEvent->getStart()->format('Y-m-d');
    }
}
