<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\BookPoll;
use Plugin\Bookclub\Entity\PollStatus;

/**
 * @extends ServiceEntityRepository<BookPoll>
 */
class BookPollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookPoll::class);
    }

    public function findActivePoll(): ?BookPoll
    {
        return $this->findOneBy(['status' => PollStatus::Active]);
    }

    public function findLatestClosed(): ?BookPoll
    {
        return $this->findOneBy(
            ['status' => PollStatus::Closed],
            ['createdAt' => 'DESC']
        );
    }
}
