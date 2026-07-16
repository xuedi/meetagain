<?php declare(strict_types=1);

namespace Plugin\Voting\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Voting\Entity\PollOption;

/**
 * @extends ServiceEntityRepository<PollOption>
 */
class PollOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PollOption::class);
    }
}
