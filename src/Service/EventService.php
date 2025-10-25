<?php
declare(strict_types=1);

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
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use RRule\RRule;
use RuntimeException;

readonly class EventService
{
    public function __construct(private EventRepository $repo, private EntityManagerInterface $em)
    {
    }

    public function getFilteredList(
        EventFilterTime $time,
        EventFilterSort $sort,
        EventTypes $type,
        EventFilterRsvp $rsvp,
    ): array {
        $criteria = new Criteria();
        $criteria->orderBy(['start' => $sort->value]);
        $criteria->where(
            match ($time) { // TODO: all should be a dummy, no idea how
                EventFilterTime::All => Criteria::expr()->not(Criteria::expr()->eq('id', 0)),
                EventFilterTime::Past => Criteria::expr()->lte('start', new DateTime()),
                EventFilterTime::Future => Criteria::expr()->gte('start', new DateTime()),
            }
        );
        $criteria->andWhere(
            match ($type) {
                EventTypes::All => Criteria::expr()->not(Criteria::expr()->eq('id', 0)),
                EventTypes::Regular => Criteria::expr()->eq('type', EventTypes::Regular->value),
                EventTypes::Outdoor => Criteria::expr()->eq('type', EventTypes::Outdoor->value),
                EventTypes::Dinner => Criteria::expr()->eq('type', EventTypes::Dinner->value),
            }
        );
        $criteria->andWhere(Criteria::expr()->eq('published', true));
        $result = $this->repo->matching($criteria)->toArray();

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
        if ($event->getRecurringRule() !== null) { // is recurring, must be the parent
            $parent = clone $event;
        } else {
            if ($event->getRecurringOf() !== null) { // has parent, load parent
                $parent = $this->repo->findOneBy(['id' => $event->getRecurringOf()]);
            } else { // no parent, no recurring, nothing to do
                return 0;
            }
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
        if (!($event->getRecurringRule() instanceof EventIntervals)) {
            return;
        }

        $skipFirst = true;
        $today = new DateTime();
        $recurringRule = $event->getRecurringRule();
        $ruleInterval = EventIntervals::BiMonthly === $recurringRule ? 2 : 1;
        $ruleFrequency = match ($recurringRule) {
            EventIntervals::Daily => RRule::DAILY,
            EventIntervals::Weekly, EventIntervals::BiMonthly => RRule::WEEKLY,
            EventIntervals::Monthly => RRule::MONTHLY,
            EventIntervals::Yearly => RRule::YEARLY,
            default => throw new RuntimeException('Unknown EventIntervals'),
        };

        $rrule = new RRule([
            'freq' => $ruleFrequency,
            'interval' => $ruleInterval,
            'dtstart' => $this->getLastRecurringEventDate($event),
            'until' => (match ($recurringRule) {
                EventIntervals::Daily => $today->modify('+2 weeks'),
                EventIntervals::Weekly,
                $today->modify('+3 weeks'),
                EventIntervals::BiMonthly,
                $today->modify('+5 weeks'),
                EventIntervals::Monthly,
                => $today->modify('+3 months'),
                EventIntervals::Yearly => $today->modify('+3 years'),
                default => throw new RuntimeException('Unknown EventIntervals'),
            })->format('Y-m-d'),
        ]);

        foreach ($rrule as $occurrence) {
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }
            if ($occurrence < new DateTime()) {
                continue;
            }

            $recurringEvent = new Event();
            $recurringEvent->setUser($event->getUser());
            $recurringEvent->setPublished($event->isPublished());
            $recurringEvent->setFeatured(false);
            $recurringEvent->setLocation($event->getLocation());
            $recurringEvent->setPreviewImage($event->getPreviewImage());
            $recurringEvent->setInitial(false);
            $recurringEvent->setStart($this->updateDate($event->getStart(), $occurrence));
            $recurringEvent->setStop($this->updateDate($event->getStop(), $occurrence));
            $recurringEvent->setRecurringOf($event->getId());
            $recurringEvent->setRecurringRule(null);
            $recurringEvent->setCreatedAt(new DateTimeImmutable());
            $recurringEvent->setHost($event->getHost());
            $recurringEvent->setType($event->getType());

            foreach ($event->getTranslation() as $eventTranslation) {
                $newEventTranslation = new EventTranslation();
                $newEventTranslation->setEvent($eventTranslation->getEvent());
                $newEventTranslation->setLanguage($eventTranslation->getLanguage());
                $newEventTranslation->setTitle($eventTranslation->getTitle());
                $newEventTranslation->setTeaser($eventTranslation->getTeaser());
                $newEventTranslation->setDescription($eventTranslation->getDescription());

                $this->em->persist($newEventTranslation);
                $recurringEvent->addTranslation($newEventTranslation);
            }

            $this->em->persist($recurringEvent);
            $this->em->flush();
        }
    }

    private function updateDate(DateTimeInterface $target, DateTime $occurrence): DateTimeInterface
    {
        $newDate = clone $target;
        $newDate->setDate(
            year: (int)$occurrence->format('Y'),
            month: (int)$occurrence->format('m'),
            day: (int)$occurrence->format('d'),
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
