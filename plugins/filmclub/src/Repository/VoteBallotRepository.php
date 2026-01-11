<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\Vote;
use Plugin\Filmclub\Entity\VoteBallot;

/**
 * @extends ServiceEntityRepository<VoteBallot>
 */
class VoteBallotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoteBallot::class);
    }

    public function save(VoteBallot $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByVoteAndMember(Vote $vote, int $memberId): ?VoteBallot
    {
        return $this->findOneBy([
            'vote' => $vote,
            'memberId' => $memberId,
        ]);
    }

    public function hasMemberVoted(Vote $vote, int $memberId): bool
    {
        return $this->findByVoteAndMember($vote, $memberId) !== null;
    }
}
