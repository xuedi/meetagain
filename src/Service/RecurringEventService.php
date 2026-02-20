<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTranslation;
use App\Enum\EntityAction;
use App\Repository\EventRepository;
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
    ) {}

    public function extentRecurringEvents(): void
    {
        $eventIds = array_map(static fn(Event $e) => $e->getId(), $this->repo->findAllRecurring());

        foreach ($eventIds as $eventId) {
            $this->em->clear();
            $event = $this->repo->find($eventId);
            if ($event !== null) {
                $this->fillRecurringEvents($event);
            }
        }
    }

    public function updateRecurringEvents(Event $event): int
    {
        if ($event->getRecurringRule() instanceof EventIntervals) {
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

        foreach ($children as $child) {
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
        }
        $this->em->flush();

        return count($children);
    }

    private function fillRecurringEvents(Event $event): void
    {
        if (!$event->getRecurringRule() instanceof EventIntervals) {
            return;
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
    }

    private function createRRule(Event $event, EventIntervals $recurringRule): RRule
    {
        $today = new DateTime();
        $ruleInterval = EventIntervals::BiMonthly === $recurringRule ? 2 : 1;
        $ruleFrequency = match ($recurringRule) {
            EventIntervals::Daily => RRule::DAILY,
            EventIntervals::Weekly, EventIntervals::BiMonthly => RRule::WEEKLY,
            EventIntervals::Monthly => RRule::MONTHLY,
            EventIntervals::Yearly => RRule::YEARLY,
        };

        return new RRule([
            'freq' => $ruleFrequency,
            'interval' => $ruleInterval,
            'dtstart' => $this->getLastRecurringEventDate($event),
            'until' => (match ($recurringRule) {
                EventIntervals::Daily => (clone $today)->modify('+2 weeks'),
                EventIntervals::Weekly => (clone $today)->modify('+3 months'),
                EventIntervals::BiMonthly => (clone $today)->modify('+6 months'),
                EventIntervals::Monthly => (clone $today)->modify('+6 months'),
                EventIntervals::Yearly => (clone $today)->modify('+3 years'),
            })->format('Y-m-d'),
        ]);
    }

    private function createRecurringEvent(Event $parent, DateTime $occurrence): Event
    {
        $recurringEvent = new Event();
        $recurringEvent->setUser($parent->getUser());
        $recurringEvent->setPublished($parent->isPublished());
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
