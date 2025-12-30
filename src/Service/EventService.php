<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventIntervals;
use App\Entity\EventTranslation;
use App\Entity\EventTypes;
use App\Repository\EventRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use RRule\RRule;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class EventService
{
    public function __construct(
        private EventRepository $repo,
        private EntityManagerInterface $em,
        private EmailService $emailService,
    ) {
    }

    public function getFilteredList(
        EventFilterTime $time,
        EventFilterSort $sort,
        EventTypes $type,
        EventFilterRsvp $rsvp,
        ?UserInterface $user = null,
    ): array {
        $result = $this->repo->findByFilters($time, $sort, $type, $user, $rsvp);

        return $this->structureList($result);
    }

    public function extentRecurringEvents(): void
    {
        $events = $this->repo->findAllRecurring();
        foreach ($events as $event) {
            $this->fillRecurringEvents($event);
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

        $children = $this->repo->findFollowUpEvents(
            parentEventId: $parent->getId(),
            greaterThan: $event->getStart(),
        );

        foreach ($children as $child) {
            $child->setLocation($event->getLocation());
            $child->setPreviewImage($event->getPreviewImage());
            foreach ($event->getTranslation() as $eventTranslation) {
                $childTranslation = $child->findTranslation($eventTranslation->getLanguage());
                if ($childTranslation === null) {
                    // Child doesn't have this language, skip
                    continue;
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

    public function cancelEvent(Event $event): void
    {
        $event->setCanceled(true);
        $this->em->persist($event);
        $this->em->flush();

        foreach ($event->getRsvp() as $user) {
            $this->emailService->prepareEventCanceledNotification($user, $event);
        }
        $this->emailService->sendQueue();
    }

    public function uncancelEvent(Event $event): void
    {
        $event->setCanceled(false);
        $this->em->persist($event);
        $this->em->flush();
    }

    private function fillRecurringEvents(Event $event): void
    {
        if (!($event->getRecurringRule() instanceof EventIntervals)) {
            return;
        }

        $rrule = $this->createRRule($event, $event->getRecurringRule());

        $skipFirst = true;
        foreach ($rrule as $occurrence) {
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }
            if ($occurrence < new DateTime()) {
                continue;
            }

            $this->em->persist($this->createRecurringEvent($event, $occurrence));
        }

        $this->em->flush();
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
                EventIntervals::Weekly => (clone $today)->modify('+3 weeks'),
                EventIntervals::BiMonthly => (clone $today)->modify('+5 weeks'),
                EventIntervals::Monthly => (clone $today)->modify('+3 months'),
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
        $recurringEvent->setHost($parent->getHost());
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

    private function structureList(array $events): array
    {
        $structuredList = [];
        foreach ($events as $event) {
            $key = $event->getStart()->format('Y-m');
            if (!isset($structuredList[$key])) {
                $structuredList[$key] = [
                    'year' => $event->getStart()->format('Y'),
                    'month' => $event->getStart()->format('F'),
                    'events' => [],
                ];
            }
            $structuredList[$key]['events'][] = $event;
        }

        return $structuredList;
    }
}
