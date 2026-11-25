<?php declare(strict_types=1);

namespace Plugin\Fediverse\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Fediverse\Entity\PendingEvent;

/**
 * @extends ServiceEntityRepository<PendingEvent>
 */
class PendingEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PendingEvent::class);
    }

    /** @return list<PendingEvent> */
    public function findOldest(int $budget): array
    {
        return $this->findBy([], ['queuedAt' => 'ASC'], $budget);
    }
}
