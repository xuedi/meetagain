<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Event> */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    // TODO: get via builder straight as keyValue
    public function getEventNameList(string $language): array
    {
        $list = [];
        foreach ($this->findAll() as $event) {
            $list[$event->getId()] = $event->getTitle($language);
        }

        return $list;
    }

    // TODO: already preload translations
    public function getUpcomingEvents(int $number = 3): array
    {
        $query = $this->getEntityManager()
            ->createQuery('SELECT e
            FROM App\Entity\Event e
            WHERE e.start > :date
            ORDER BY e.start ASC')
            ->setParameter('date', new DateTime())
            ->setMaxResults($number);

        return $query->getResult();
    }

    // TODO: already preload translations
    public function getPastEvents(int $number = 3, null|User $user = null): array
    {
        $query = $this->getEntityManager()
            ->createQuery('SELECT e
            FROM App\Entity\Event e
            WHERE e.start < :date
            ORDER BY e.start DESC')
            ->setParameter('date', new DateTime())
            ->setMaxResults($number);

        return $query->getResult();
    }

    // TODO: already preload translations
    public function findAllRecurring(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $query = $qb
            ->select('e')
            ->from(Event::class, 'e')
            ->where($qb->expr()->isNotNull('e.recurringRule'))
            ->orderBy('e.start', 'ASC')
            ->getQuery();

        return $query->getResult();
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
        $now = new DateTime()->setTime(0, 0, 0);
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
        $all = $this->findBy(['initial' => true]);
        $list = [];
        foreach ($all as $event) {
            $list[$event->getTitle($locale)] = $event->getId();
        }

        return $list;
    }
}
