<?php declare(strict_types=1);

namespace Plugin\Fediverse\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Follower;

/**
 * @extends ServiceEntityRepository<Follower>
 */
class FollowerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follower::class);
    }

    public function findOneFor(Account $account, string $actorId): ?Follower
    {
        return $this->findOneBy(['account' => $account, 'actorId' => $actorId]);
    }

    public function findOneByFollowId(string $followId, string $actorId): ?Follower
    {
        return $this->findOneBy(['followId' => $followId, 'actorId' => $actorId]);
    }

    /** @return list<Follower> */
    public function findByActorId(string $actorId): array
    {
        return $this->findBy(['actorId' => $actorId]);
    }

    /** @return list<string> one inbox per server where it shares one, the follower's own otherwise */
    public function findInboxesFor(Account $account): array
    {
        $inboxes = array_map(static fn(Follower $follower): string => $follower->getSharedInbox() ?? $follower->getInbox(), $this->findBy([
            'account' => $account,
        ]));

        return array_values(array_unique($inboxes));
    }

    public function countFor(Account $account): int
    {
        return $this->count(['account' => $account]);
    }

    /** @param callable(string): bool $isBlocked */
    public function removeOnHosts(callable $isBlocked): int
    {
        $rows = $this->createQueryBuilder('f')->select('f.id', 'f.inbox', 'f.sharedInbox')->getQuery()->getArrayResult();
        $ids = [];
        foreach ($rows as $row) {
            foreach ([$row['inbox'], $row['sharedInbox']] as $url) {
                if ($url !== null && $isBlocked(strtolower((string) parse_url($url, PHP_URL_HOST)))) {
                    $ids[] = $row['id'];
                    break;
                }
            }
        }

        if ($ids === []) {
            return 0;
        }

        $this
            ->createQueryBuilder('f')
            ->delete()
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();

        return count($ids);
    }

    public function removeFor(Account $account): void
    {
        $this
            ->createQueryBuilder('f')
            ->delete()
            ->where('f.account = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->execute();
    }
}
