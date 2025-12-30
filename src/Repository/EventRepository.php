<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends ServiceEntityRepository<Event> */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return Event[]
     */
    public function findByFilters(
        EventFilterTime $time,
        EventFilterSort $sort,
        EventTypes $type,
        ?UserInterface $user = null,
        ?EventFilterRsvp $rsvp = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->andWhere('e.published = :published')
            ->setParameter('published', true);

        match ($time) {
            EventFilterTime::Past => $qb->andWhere('e.start <= :now')->setParameter('now', new DateTime()),
            EventFilterTime::Future => $qb->andWhere('e.start >= :now')->setParameter('now', new DateTime()),
            EventFilterTime::All => null,
        };

        if ($type !== EventTypes::All) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type->value);
        }

        if ($rsvp instanceof \App\Entity\EventFilterRsvp && $rsvp !== EventFilterRsvp::All && $user instanceof \App\Entity\User) {
            if ($rsvp === EventFilterRsvp::My) {
                $qb->innerJoin('e.rsvp', 'u', 'WITH', 'u.id = :userId')
                    ->setParameter('userId', $user->getId());
            }
            // Friends filtering not yet implemented
        }

        $qb->orderBy('e.start', $sort->value);

        return $qb->getQuery()->getResult();
    }

    public function getEventNameList(string $language): array
    {
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($events as $event) {
            $list[$event->getId()] = $event->getTitle($language);
        }

        return $list;
    }

    /**
     * @return array<Event>
     */
    public function getUpcomingEvents(int $number = 3): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.start > :date')
            ->setParameter('date', new DateTime())
            ->orderBy('e.start', 'ASC')
            ->setMaxResults($number)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Event>
     */
    public function getPastEvents(int $number = 3): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.start < :date')
            ->setParameter('date', new DateTime())
            ->orderBy('e.start', 'DESC')
            ->setMaxResults($number)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Event>
     */
    public function findAllRecurring(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.recurringRule IS NOT NULL')
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Event>
     */
    public function findFollowUpEvents(int $parentEventId, DateTimeInterface $greaterThan): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $query = $qb
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.recurringOf = :parentEvent')
            ->andWhere('e.start > :greaterThan')
            ->setParameter('parentEvent', $parentEventId)
            ->setParameter('greaterThan', $greaterThan)
            ->orderBy('e.start', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    public function getNextEventId(): null|int
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        foreach ($this->findBy([], ['start' => 'ASC']) as $event) {
            /** @var DateTime $start */
            $start = clone $event->getStart();
            $start->setTime(0, 0);
            if ($start > $now) { // first bigger than today
                return $event->getId();
            }
        }
        return null;
    }

    public function getChoices(string $locale): array
    {
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.initial = :initial')
            ->setParameter('initial', true)
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($events as $event) {
            $list[$event->getTitle($locale)] = $event->getId();
        }

        return $list;
    }

    /**
     * @return array<Event>
     */
    public function getPastEventsWithoutPhotos(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.images', 'i')
            ->where('e.start < :date')
            ->andWhere('i.id IS NULL')
            ->setParameter('date', new DateTime())
            ->orderBy('e.start', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getRecurringCount(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.recurringRule IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
